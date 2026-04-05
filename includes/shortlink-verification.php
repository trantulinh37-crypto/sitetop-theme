<?php
/**
 * LinkNgon V2 - Shortlink Verification & Balance
 * CLAUDE.md Flow 1: taskify_verify_and_pay() exact validation order
 * 
 * SOURCE OF TRUTH: transactions table
 * KHÔNG cộng refund_amount (double-counting prevention)
 * Session: uses session_id (32-char)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   VERIFY AND PAY (Flow 1 - exact order from CLAUDE.md)
   ============================================================ */

function linkngon_verify_and_pay( $session_id, $code ) {
    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';
    $ip = linkngon_get_real_ip();

    // ── PRE-TRANSACTION VALIDATION (exact order) ──

    // Line 248: IP block check
    if ( linkngon_is_ip_blocked( $ip ) ) {
        return new WP_Error( 'ip_blocked', 'IP bị chặn' );
    }

    // Line 256: Find visit by session_id
    $visit = $wpdb->get_row( $wpdb->prepare(
        "SELECT v.*, kc.price_per_view, kc.user_reward as camp_user_reward,
                kc.onsite_time as camp_onsite, kc.traffic_type,
                kc.customer_id, kc.daily_traffic, kc.status as camp_status,
                kc.fixed_code, kc.id as camp_id, kc.order_id as camp_order_id,
                kc.campaign_type,
                sl.original_url, sl.id as sl_id
         FROM {$p}shortlink_visits v
         LEFT JOIN {$p}keyword_campaigns kc ON v.campaign_id = kc.id
         LEFT JOIN {$p}user_shortlinks sl ON v.shortlink_id = sl.id
         WHERE v.session_id = %s", $session_id
    ));
    if ( ! $visit ) return new WP_Error( 'not_found', 'Session không tồn tại' );

    // Line 266: Check reward_paid
    if ( $visit->reward_paid ) {
        return new WP_Error( 'already_used', 'Đã xác minh', array( 'target_url' => $visit->original_url ) );
    }

    // Line 271: User banned check
    if ( $visit->user_id > 0 && get_user_meta( $visit->user_id, 'linkngon_banned', true ) ) {
        return new WP_Error( 'banned', 'Tài khoản bị khóa' );
    }

    // Line 279: Visit age < 2 hours (7200s)
    $created_at = strtotime( $visit->created_at );
    $now = strtotime( linkngon_current_time() );
    $elapsed = $now - $created_at;
    if ( $elapsed > 7200 ) return new WP_Error( 'expired', 'Phiên đã hết hạn (>2h)' );

    // Line 294-329: Campaign checks
    $is_nocode = ( $visit->traffic_type === 'nocode' );
    // If campaign has fixed_code, treat as nocode
    if ( ! empty( $visit->fixed_code ) ) $is_nocode = true;
    $should_pay_reward = true;
    $should_pay_customer = true;
    $skip_reasons = array();

    if ( ! $visit->camp_id ) {
        $should_pay_reward = false;
        $should_pay_customer = false;
        $skip_reasons[] = 'no_campaign';
    } elseif ( $visit->camp_status !== 'active' ) {
        $should_pay_reward = false;
        $should_pay_customer = false;
        $skip_reasons[] = 'campaign_inactive';
    }

    // Line 340-380: TIME CHECK (skip for nocode)
    if ( ! $is_nocode ) {
        $onsite = (int) ( $visit->camp_onsite ?? $visit->onsite_time ?? 70 );
        $min_required = max( $onsite - 5, 10 );

        if ( $elapsed < $min_required ) {
            return new WP_Error( 'too_fast', 'Chưa đủ thời gian', array(
                'remaining' => $min_required - $elapsed,
            ));
        }

        // Code ready transient check
        if ( ! get_transient( 'linkngon_widget_code_ready_' . $session_id ) ) {
            return new WP_Error( 'code_not_ready', 'Code chưa sẵn sàng' );
        }
    }

    // Line 399-441: TRAFFIC-SPECIFIC CHECKS
    // Determine campaign_type (keyword_search / traffic_direct / traffic_social)
    $campaign_type = $visit->campaign_type ?? 'keyword_search';
    // Fallback: infer from task_type if available
    if ( $campaign_type === 'keyword_search' && $visit->camp_order_id ) {
        $order_task_type = $wpdb->get_var( $wpdb->prepare(
            "SELECT task_type FROM {$p}customer_orders WHERE id = %d", $visit->camp_order_id
        ));
        if ( $order_task_type ) $campaign_type = $order_task_type;
    }

    if ( $campaign_type === 'keyword_search' ) {
        // keyword_search: from_google + url_matched (kể cả nocode)
        if ( ! $visit->from_google || ! $visit->url_matched ) {
            $should_pay_reward = false;
            $skip_reasons[] = 'google_check_failed';
        }
    } elseif ( $campaign_type === 'traffic_social' && ! $is_nocode ) {
        // traffic_social: url_matched only (no source check)
        if ( ! $visit->url_matched ) {
            $should_pay_reward = false;
            $skip_reasons[] = 'url_not_matched';
        }
    } elseif ( $campaign_type === 'traffic_direct' && ! $is_nocode ) {
        // traffic_direct: url_matched only
        if ( ! $visit->url_matched ) {
            $should_pay_reward = false;
            $skip_reasons[] = 'url_not_matched';
        }
    }

    // Line 444-478: CODE CHECK
    if ( $is_nocode ) {
        // Case-SENSITIVE comparison with fixed_code
        $fixed_code = $visit->fixed_code ?? '';
        if ( empty( $fixed_code ) ) {
            return new WP_Error( 'no_fixed_code', 'Mã cố định chưa được cấu hình.' );
        }
        if ( $code !== $fixed_code ) {
            return new WP_Error( 'wrong_code', 'Mã không đúng (case-sensitive)' );
        }
    } else {
        // Case-INSENSITIVE
        if ( strtoupper( $code ) !== strtoupper( $visit->verify_code ) ) {
            return new WP_Error( 'wrong_code', 'Mã xác minh không đúng' );
        }
        // Code expiry: 10 min transient
        $cached = get_transient( 'linkngon_verify_code_' . $session_id );
        if ( $cached === false ) {
            return new WP_Error( 'code_expired', 'Mã đã hết hạn (10 phút)' );
        }
    }

    // Line 551-602: IP CHECKS
    // Check pre-marked ip_changed flag first (from previous steps)
    $ip_changed = false;
    if ( (int) ( $visit->ip_changed ?? 0 ) === 1 ) {
        $ip_changed = true;
        $should_pay_reward = false;
        $skip_reasons[] = 'ip_changed_premarked';
    } elseif ( $ip !== ( $visit->original_ip ?? $visit->ip_address ) ) {
        $ip_changed = true;
        $should_pay_reward = false;
        $skip_reasons[] = 'ip_changed';
    }

    $ip_limit = (int) linkngon_get_option( 'shortlink_ip_limit_24h', 2 );
    $today = date( 'Y-m-d', strtotime( linkngon_current_time() ) );
    $ip_daily = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits
         WHERE ip_address = %s AND step = 'verified' AND DATE(created_at) = %s",
        $ip, $today
    ));
    if ( $ip_daily >= $ip_limit ) {
        $should_pay_reward = false;
        $skip_reasons[] = 'ip_limit_exceeded';
    }

    // Line 605: Adblock check
    if ( $visit->adblock_detected ) {
        $should_pay_reward = false;
        $skip_reasons[] = 'adblock';
    }

    // Line 622: Bypass check - 3-zone system from production:
    // Zone 1 (elapsed < onsite_time - 5): BLOCKED by time check above
    // Zone 2 (onsite_time - 5 <= elapsed < onsite_time): Verify OK, NO reward
    // Zone 3 (elapsed >= onsite_time): Verify OK + reward
    $is_bypass = false;
    $verify_traffic_type = $visit->traffic_type ?? '1step';
    if ( $should_pay_reward && $verify_traffic_type !== 'nocode' ) {
        $onsite = (int) ( $visit->camp_onsite ?? $visit->onsite_time ?? 70 );
        if ( $elapsed < $onsite ) {
            $is_bypass = true;
            $should_pay_reward = false;
            $skip_reasons[] = 'bypass_detected';
        }
    }

    // Line 639-675: Daily traffic limit
    if ( $visit->camp_id && $visit->daily_traffic > 0 ) {
        $daily_completed = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}shortlink_visits
             WHERE campaign_id = %d AND step = 'verified' AND DATE(created_at) = %s",
            $visit->camp_id, $today
        ));
        if ( $daily_completed >= $visit->daily_traffic ) {
            $should_pay_reward = false;
            $should_pay_customer = false;
            $skip_reasons[] = 'daily_limit_reached';
        }
    }

    // Line 681-748: Customer balance check
    if ( $visit->customer_id && $should_pay_customer ) {
        $cust_balance = linkngon_get_customer_balance_amount( $visit->customer_id );
        if ( $cust_balance === false ) {
            // SQL error → skip payment (safety)
            $should_pay_customer = false;
            $skip_reasons[] = 'customer_balance_error';
        } else {
            $min_balance = (int) linkngon_get_option( 'customer_min_balance', 20000 );
            $cost = (float) $visit->price_per_view;
            if ( $cust_balance <= $min_balance || $cust_balance < $cost ) {
                $should_pay_customer = false;
                $should_pay_reward = false;
                $skip_reasons[] = 'customer_insufficient';
                // Auto-pause ALL customer campaigns
                linkngon_auto_pause_customer_campaigns( $visit->customer_id );
            }
        }
    }

    // ── DATABASE TRANSACTION (Line 809-1050) ──
    $wpdb->query( 'START TRANSACTION' );
    try {
        // Line 814: LOCK visit FOR UPDATE → re-check
        $locked = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$p}shortlink_visits WHERE session_id = %s FOR UPDATE",
            $session_id
        ));
        if ( ! $locked || $locked->reward_paid || $locked->step === 'verified' ) {
            $wpdb->query( 'ROLLBACK' );
            // Graceful: concurrent request already paid - return success for redirect
            return array(
                'success'    => true,
                'target_url' => $visit->original_url,
                'reward'     => 0,
                'paid'       => false,
            );
        }

        // Line 836: RECHECK daily limit INSIDE transaction (race condition)
        if ( $visit->camp_id && $visit->daily_traffic > 0 ) {
            $recheck_daily = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$p}shortlink_visits
                 WHERE campaign_id = %d AND step = 'verified' AND DATE(created_at) = %s",
                $visit->camp_id, $today
            ));
            if ( $recheck_daily >= $visit->daily_traffic ) {
                $should_pay_reward = false;
                $should_pay_customer = false;
            }
        }

        // Line 882-911: Customer payment
        $customer_paid = false;
        if ( $should_pay_customer && $visit->customer_id && $visit->price_per_view > 0 ) {
            // LOCK customer_balance FOR UPDATE
            $cbal = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$p}customer_balance WHERE user_id = %d FOR UPDATE",
                $visit->customer_id
            ));

            if ( $cbal && $cbal->balance >= $visit->price_per_view ) {
                $cost = (float) $visit->price_per_view;

                // Deduct customer balance atomically
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$p}customer_balance SET balance = balance - %f, total_spent = total_spent + %f, updated_at = %s WHERE user_id = %d",
                    $cost, $cost, linkngon_current_time(), $visit->customer_id
                ));

                // Log customer_transaction (type='campaign_view')
                $new_cbal = $cbal->balance - $cost;
                $wpdb->insert( "{$p}customer_transactions", array(
                    'customer_id' => $visit->customer_id,
                    'type' => 'campaign_view', 'amount' => -$cost,
                    'reference_id' => $locked->id, 'reference_type' => 'visit',
                    'description' => "View campaign #{$visit->camp_id}",
                    'balance_after' => $new_cbal,
                    'created_at' => linkngon_current_time(),
                ));

                $customer_paid = true;

                // Line 919: Update order.amount_spent
                if ( $visit->camp_order_id ) {
                    $wpdb->query( $wpdb->prepare(
                        "UPDATE {$p}customer_orders SET amount_spent = amount_spent + %f, completed = completed + 1, updated_at = %s WHERE id = %d",
                        $cost, linkngon_current_time(), $visit->camp_order_id
                    ));
                }

                // Line 926: Auto-pause if balance <= min
                $min_balance = (int) linkngon_get_option( 'customer_min_balance', 20000 );
                if ( $new_cbal <= $min_balance + $cost ) {
                    linkngon_auto_pause_customer_campaigns( $visit->customer_id );
                }
            } else {
                $should_pay_reward = false;
            }
        }

        // Line 992: User reward
        // Only pay user when customer actually paid (or non-campaign shortlink)
        $reward_amount = 0;
        $user_paid = false;
        if ( $should_pay_reward && $visit->user_id > 0 ) {
            $can_pay_user = ! $visit->camp_id || $customer_paid;

            if ( $can_pay_user ) {
                // Determine reward (Flow 8)
                $camp_obj = (object) array(
                    'user_reward' => $visit->camp_user_reward,
                    'traffic_type' => $visit->traffic_type,
                    'campaign_type' => $visit->campaign_type ?? 'keyword_search',
                );
                $reward_amount = linkngon_get_reward_amount( $camp_obj );

                // Add user balance + transaction
                linkngon_add_user_balance( $visit->user_id, $reward_amount, 'shortlink_reward',
                    'Thưởng shortlink #' . $locked->id, $locked->id, 'visit' );
                $user_paid = true;
            } else {
                $should_pay_reward = false;
                $skip_reasons[] = 'customer_not_paid';
            }
        }

        // Line 997: Update shortlink stats (only when user actually paid)
        if ( $visit->sl_id && $user_paid ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$p}user_shortlinks SET total_completed = total_completed + 1, total_earnings = total_earnings + %f WHERE id = %d",
                $reward_amount, $visit->sl_id
            ));
        }

        // Line 1019-1027: Campaign/order counters (only when customer paid)
        if ( $visit->camp_id && $customer_paid ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$p}keyword_campaigns SET completed = completed + 1 WHERE id = %d", $visit->camp_id
            ));
        }

        // Line 1040: Update visit
        $wpdb->update( "{$p}shortlink_visits", array(
            'step'            => 'verified',
            'verified_at'     => linkngon_current_time(),
            'reward_paid'     => ( $should_pay_reward && $user_paid ) ? 1 : 0,
            'customer_paid'   => $customer_paid ? 1 : 0,
            'reward_amount'   => $user_paid ? $reward_amount : 0,
            'completion_time' => $elapsed,
            'ip_changed'      => $ip_changed ? 1 : 0,
            'is_bypass'       => $is_bypass ? 1 : 0,
            'ip_limit_exceeded' => in_array( 'ip_limit_exceeded', $skip_reasons ) ? 1 : 0,
        ), array( 'session_id' => $session_id ) );

        // Line 1050: COMMIT
        $wpdb->query( 'COMMIT' );

        // Cleanup transients
        delete_transient( 'linkngon_widget_code_ready_' . $session_id );
        delete_transient( 'linkngon_verify_code_' . $session_id );
        delete_transient( 'linkngon_google_clicked_' . $session_id );

        return array(
            'success'    => true,
            'target_url' => $visit->original_url,
            'reward'     => $user_paid ? $reward_amount : 0,
            'paid'       => $user_paid,
        );

    } catch ( Exception $e ) {
        $wpdb->query( 'ROLLBACK' );
        return new WP_Error( 'error', $e->getMessage() );
    }
}

/* ============================================================
   USER BALANCE - SOURCE OF TRUTH: transactions table
   Flow 8b from CLAUDE.md
   ============================================================ */

/**
 * Get available balance from transactions
 * type IN ('shortlink_reward', 'earn') for total_earned
 */
function linkngon_get_user_balance_amount( $user_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';

    $total_earned = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}transactions WHERE user_id=%d AND type IN ('shortlink_reward','earn')", $user_id ));
    $total_withdrawn = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}withdrawals WHERE user_id=%d AND status IN ('completed','cancelled')", $user_id ));
    $pending_wd = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}withdrawals WHERE user_id=%d AND status IN ('pending','approved')", $user_id ));
    $other_deductions = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}transactions WHERE user_id=%d AND type='withdraw' AND (reference_type IS NULL OR reference_type != 'withdrawal')", $user_id ));

    return max( 0, $total_earned - $total_withdrawn - $pending_wd - abs($other_deductions) );
}

/**
 * Add user balance + insert transaction
 * 1. UPDATE balance, total_earned
 * 2. If 0 rows → INSERT IGNORE
 * 3. If INSERT skipped (race) → RETRY UPDATE
 */
function linkngon_add_user_balance( $user_id, $amount, $type = 'shortlink_reward', $description = '', $ref_id = null, $ref_type = null ) {
    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';

    // 1. Try UPDATE
    $updated = $wpdb->query( $wpdb->prepare(
        "UPDATE {$p}user_balance SET balance = balance + %f, total_earned = total_earned + %f, updated_at = %s WHERE user_id = %d",
        $amount, $amount, linkngon_current_time(), $user_id
    ));

    // 2. If 0 rows → INSERT IGNORE
    if ( $updated === 0 ) {
        $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO {$p}user_balance (user_id, balance, total_earned, updated_at) VALUES (%d, %f, %f, %s)",
            $user_id, $amount, $amount, linkngon_current_time()
        ));

        // 3. Race condition → RETRY UPDATE
        if ( $wpdb->rows_affected === 0 ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$p}user_balance SET balance = balance + %f, total_earned = total_earned + %f, updated_at = %s WHERE user_id = %d",
                $amount, $amount, linkngon_current_time(), $user_id
            ));
        }
    }

    // Insert transaction record
    $balance_after = linkngon_get_user_balance_amount( $user_id );
    $wpdb->insert( "{$p}transactions", array(
        'user_id'        => $user_id,
        'type'           => $type,
        'amount'         => $amount,
        'description'    => sanitize_text_field( $description ),
        'reference_id'   => $ref_id,
        'reference_type' => $ref_type,
        'balance_after'  => $balance_after,
        'created_at'     => linkngon_current_time(),
    ));

    return true;
}

/**
 * Sync user_balance cache from transactions (fix drift)
 */
function linkngon_sync_user_balance( $user_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';

    $balance = linkngon_get_user_balance_amount( $user_id );
    $earned = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}transactions WHERE user_id=%d AND type IN ('shortlink_reward','earn')", $user_id ));

    $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}user_balance WHERE user_id=%d", $user_id ) );
    $data = array( 'balance' => $balance, 'total_earned' => $earned, 'updated_at' => linkngon_current_time() );

    if ( $existing ) $wpdb->update( "{$p}user_balance", $data, array( 'user_id' => $user_id ) );
    else { $data['user_id'] = $user_id; $wpdb->insert( "{$p}user_balance", $data ); }
}

/* ============================================================
   CUSTOMER BALANCE - SOURCE OF TRUTH: deposits + customer_transactions
   CLAUDE.md: customer_balance uses user_id (NOT customer_id)
   Returns FALSE on SQL error (not 0) — callers must check!
   ============================================================ */

function linkngon_get_customer_balance_amount( $user_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';

    // Column safety: verify user_id exists
    $has_uid = $wpdb->get_results( "SHOW COLUMNS FROM {$p}customer_balance LIKE 'user_id'" );
    if ( empty( $has_uid ) ) return false; // Safety

    $deposited = $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount + bonus_amount),0) FROM {$p}customer_deposits WHERE customer_id=%d AND status='approved'", $user_id ));
    if ( $deposited === null ) return false;

    $bonus = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}customer_transactions WHERE customer_id=%d AND type='bonus' AND amount > 0", $user_id ));
    $spent = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(ABS(SUM(amount)),0) FROM {$p}customer_transactions WHERE customer_id=%d AND type='campaign_view' AND amount < 0", $user_id ));
    $deductions = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(ABS(SUM(amount)),0) FROM {$p}customer_transactions WHERE customer_id=%d AND type='deduction' AND amount < 0", $user_id ));

    return max( 0, (float) $deposited + $bonus - $spent - $deductions );
}

function linkngon_sync_customer_balance( $user_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';

    $balance = linkngon_get_customer_balance_amount( $user_id );
    if ( $balance === false ) return; // SQL error safety

    $deposited = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount + bonus_amount),0) FROM {$p}customer_deposits WHERE customer_id=%d AND status='approved'", $user_id ));
    $spent = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(ABS(SUM(amount)),0) FROM {$p}customer_transactions WHERE customer_id=%d AND type='campaign_view' AND amount < 0", $user_id ));

    $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}customer_balance WHERE user_id=%d", $user_id ) );
    $data = array( 'balance'=>$balance, 'total_deposited'=>$deposited, 'total_spent'=>$spent, 'updated_at'=>linkngon_current_time() );

    if ( $existing ) $wpdb->update( "{$p}customer_balance", $data, array( 'user_id' => $user_id ) );
    else { $data['user_id'] = $user_id; $wpdb->insert( "{$p}customer_balance", $data ); }
}

/** Auto-pause all campaigns of a customer */
function linkngon_auto_pause_customer_campaigns( $customer_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';
    $now = linkngon_current_time();
    $wpdb->query( $wpdb->prepare( "UPDATE {$p}keyword_campaigns SET status='paused', updated_at=%s WHERE customer_id=%d AND status='active'", $now, $customer_id ) );
    $wpdb->query( $wpdb->prepare( "UPDATE {$p}customer_orders SET status='paused', updated_at=%s WHERE customer_id=%d AND status='active'", $now, $customer_id ) );

    // Invalidate eligible campaigns cache
    delete_transient( 'linkngon_eligible_campaigns' );
}
