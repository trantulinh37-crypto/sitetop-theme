<?php
/**
 * LinkNgon V2 - Deposit Management (CLAUDE.md Flow 4)
 * Deposit with bonus tiers
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function linkngon_submit_deposit( $user_id, $amount, $method = 'bank' ) {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;
    $amount = floatval($amount);

    if ( $amount < 50000 ) return new WP_Error('min', 'Nạp tối thiểu 50,000đ');
    if ( $amount > 100000000 ) return new WP_Error('max', 'Nạp tối đa 100,000,000đ');

    // Rate limit
    $rate = linkngon_rate_limit_check('deposit', $user_id);
    if ( !$rate['allowed'] ) return new WP_Error('rate', 'Quá nhiều yêu cầu');

    // Calculate bonus
    $bonus_percent = 0;
    $bonus = linkngon_calculate_deposit_bonus($amount);
    $user = get_user_by('ID', $user_id);

    $wpdb->insert("{$p}customer_deposits", array(
        'customer_id'       => $user_id,
        'customer_username' => $user ? $user->user_login : '',
        'amount'            => $amount,
        'bonus_percent'     => $bonus_percent,
        'bonus_amount'      => $bonus,
        'payment_method'    => sanitize_text_field($method),
        'status'            => 'pending',
        'created_at'        => linkngon_current_time(),
    ));
    return $wpdb->insert_id ?: new WP_Error('db', 'Lỗi tạo deposit');
}

function linkngon_calculate_deposit_bonus( $amount ) {
    $tiers = json_decode( linkngon_get_option('deposit_presets', '[]'), true );
    if ( empty($tiers) ) {
        // Default tiers
        $tiers = array(
            array('amount' => 1000000, 'bonus' => 5),  // 5% for 1M+
            array('amount' => 5000000, 'bonus' => 10), // 10% for 5M+
            array('amount' => 10000000, 'bonus' => 15), // 15% for 10M+
        );
    }
    usort($tiers, function($a,$b){ return $b['amount'] - $a['amount']; });
    foreach ( $tiers as $tier ) {
        if ( $amount >= $tier['amount'] ) return $amount * ($tier['bonus'] / 100);
    }
    return 0;
}

function linkngon_approve_deposit( $deposit_id, $admin_note = '' ) {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;
    $now = linkngon_current_time();

    $wpdb->query( 'START TRANSACTION' );
    try {
        // Lock deposit FOR UPDATE → check status='pending'
        $dep = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$p}customer_deposits WHERE id=%d FOR UPDATE", $deposit_id ));
        if ( ! $dep || $dep->status !== 'pending' ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'invalid', 'Deposit không hợp lệ' );
        }

        $total = $dep->amount + $dep->bonus_amount;

        // Lock customer_balance FOR UPDATE → atomic balance update
        $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$p}customer_balance WHERE user_id=%d FOR UPDATE", $dep->customer_id ));

        // Update deposit status
        $wpdb->update( "{$p}customer_deposits", array(
            'status' => 'approved', 'note' => sanitize_text_field( $admin_note ),
            'approved_by' => get_current_user_id(), 'approved_at' => $now, 'updated_at' => $now,
        ), array( 'id' => $deposit_id ) );

        // Atomic balance update
        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$p}customer_balance SET balance = balance + %f, total_deposited = total_deposited + %f, updated_at = %s WHERE user_id = %d",
            $total, $dep->amount, $now, $dep->customer_id
        ));
        // If customer_balance row doesn't exist, create it
        if ( $updated === 0 ) {
            $wpdb->insert( "{$p}customer_balance", array(
                'user_id' => $dep->customer_id, 'balance' => $total,
                'total_deposited' => $dep->amount, 'total_spent' => 0, 'updated_at' => $now,
            ));
        }

        // Log customer transaction
        $bal = linkngon_get_customer_balance_amount( $dep->customer_id );
        $wpdb->insert( "{$p}customer_transactions", array(
            'customer_id' => $dep->customer_id, 'amount' => $total, 'type' => 'deposit',
            'reference_id' => $deposit_id, 'reference_type' => 'deposit',
            'description' => 'Nạp tiền ' . linkngon_format_money( $dep->amount ) . ( $dep->bonus_amount > 0 ? ' + bonus ' . linkngon_format_money( $dep->bonus_amount ) : '' ),
            'balance_after' => $bal, 'created_at' => $now,
        ));

        $wpdb->query( 'COMMIT' );
    } catch ( Exception $e ) {
        $wpdb->query( 'ROLLBACK' );
        return new WP_Error( 'error', $e->getMessage() );
    }

    // Auto-resume paused campaigns (outside transaction)
    linkngon_auto_resume_paused_campaigns();
    delete_transient( 'linkngon_eligible_campaigns' );

    // Email KH
    linkngon_send_deposit_approved_email( $deposit_id );

    return true;
}
