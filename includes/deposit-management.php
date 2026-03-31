<?php
/**
 * LinkNgon V2 - Deposit Management (CLAUDE.md Flow 4)
 * Deposit with bonus tiers
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function linkngon_submit_deposit( $user_id, $amount, $method = 'bank', $transaction_id = '' ) {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;
    $amount = floatval($amount);

    if ( $amount < 50000 ) return new WP_Error('min', 'Nạp tối thiểu 50,000đ');
    if ( $amount > 100000000 ) return new WP_Error('max', 'Nạp tối đa 100,000,000đ');

    // Rate limit
    $rate = linkngon_rate_limit_check('deposit', $user_id);
    if ( !$rate['allowed'] ) return new WP_Error('rate', 'Quá nhiều yêu cầu');

    // Calculate bonus
    $bonus = linkngon_calculate_deposit_bonus($amount);

    $wpdb->insert("{$p}customer_deposits", array(
        'customer_id'=>$user_id, 'amount'=>$amount, 'bonus_amount'=>$bonus,
        'method'=>sanitize_text_field($method), 'status'=>'pending',
        'transaction_id'=>sanitize_text_field($transaction_id), 'created_at'=>linkngon_current_time(),
    ));
    return $wpdb->insert_id ?: new WP_Error('db', 'Lỗi tạo deposit');
}

function linkngon_calculate_deposit_bonus( $amount ) {
    $tiers = json_decode( linkngon_get_option('deposit_tiers', '[]'), true );
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

    $dep = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$p}customer_deposits WHERE id=%d", $deposit_id));
    if (!$dep || $dep->status !== 'pending') return new WP_Error('invalid', 'Deposit không hợp lệ');

    $total = $dep->amount + $dep->bonus_amount;

    $wpdb->update("{$p}customer_deposits", array(
        'status'=>'completed', 'admin_note'=>sanitize_text_field($admin_note), 'updated_at'=>linkngon_current_time()
    ), array('id'=>$deposit_id));

    // Update customer balance
    $bal = linkngon_get_customer_balance_amount($dep->customer_id);
    $wpdb->insert("{$p}customer_transactions", array(
        'customer_id'=>$dep->customer_id, 'amount'=>$total, 'type'=>'deposit',
        'reference_id'=>$deposit_id, 'reference_type'=>'deposit',
        'description'=>'Nạp tiền ' . linkngon_format_money($dep->amount) . ($dep->bonus_amount > 0 ? ' + bonus ' . linkngon_format_money($dep->bonus_amount) : ''),
        'balance_after'=>$bal + $total, 'created_at'=>linkngon_current_time(),
    ));
    linkngon_sync_customer_balance($dep->customer_id);

    // Try auto-resume campaigns
    linkngon_auto_resume_paused_campaigns();
    return true;
}
