# SiteTop.net - Shortlink System Documentation
See CLAUDE.md for complete rules and flows.

## Flow: Publisher rút gọn link
1. Paste URL → sitetop_create_user_shortlink() → code + optional alias
2. Share shortlink → visitor clicks → page-unlock.php
3. Countdown → get code → verify → redirect to original URL
4. Publisher earns reward per verified click

## Flow: Advertiser campaign
1. Deposit → bonus tiers → admin approves
2. Create campaign → admin approves → status active
3. Distribution: weighted random (time_lag, peer_lag, carryover)
4. Each verified visit: customer balance -= price_per_view
5. Auto-pause when balance low, auto-resume when recovered

## Balance Formula (User)
available = SUM(shortlink_reward) - SUM(completed+cancelled withdrawals) - SUM(pending+approved withdrawals) - ABS(SUM(other deductions))

## Balance Formula (Customer)  
balance = SUM(approved deposits + bonus) - ABS(SUM(campaign_view charges))
