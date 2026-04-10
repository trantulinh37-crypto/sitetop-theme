<?php
/**
 * Template Name: Điều khoản sử dụng
 */
$telegram = traffictop_get_option( 'contact_telegram', '' );
$signal   = traffictop_get_option( 'contact_signal', '' );
$zalo     = traffictop_get_option( 'contact_zalo', '' );
$email    = traffictop_get_option( 'contact_email', '' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Điều khoản sử dụng - <?php bloginfo('name'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php wp_head(); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.8;
            color: #334155;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #f0fdf4 100%);
            min-height: 100vh;
        }

        /* Header */
        .page-header {
            background: linear-gradient(135deg, #0f172a, #1e3a8a);
            color: white;
            padding: 60px 20px;
            text-align: center;
        }

        .page-header .logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            margin-bottom: 20px;
        }

        .page-header .logo i {
            color: #f59e0b;
            font-size: 1.75rem;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #ffffff;
        }

        .page-header h1 i {
            color: #ffffff;
        }

        .page-header p {
            color: rgba(255,255,255,0.8);
            font-size: 1rem;
        }

        /* Container */
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px 60px;
        }

        /* Content Card */
        .content-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 50px;
            margin-top: -40px;
            position: relative;
        }

        /* Typography */
        h2 {
            color: #1e40af;
            font-size: 1.4rem;
            font-weight: 700;
            margin: 35px 0 15px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        h2:first-child {
            margin-top: 0;
        }

        h2 i {
            color: #3b82f6;
            font-size: 1.2rem;
        }

        h3 {
            font-size: 1.1rem;
            color: #334155;
            margin: 25px 0 12px;
            font-weight: 600;
        }

        p {
            margin-bottom: 15px;
            text-align: justify;
            color: #475569;
        }

        ul, ol {
            margin: 15px 0 15px 25px;
            color: #475569;
        }

        li {
            margin-bottom: 10px;
        }

        strong {
            color: #1e293b;
            font-weight: 600;
        }

        /* Alert Boxes */
        .alert-box {
            padding: 20px 25px;
            border-radius: 12px;
            margin: 25px 0;
        }

        .alert-box.warning {
            background: linear-gradient(135deg, #fef3c7, #fef9c3);
            border-left: 4px solid #f59e0b;
        }

        .alert-box.danger {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border-left: 4px solid #ef4444;
        }

        .alert-box.info {
            background: linear-gradient(135deg, #dbeafe, #e0f2fe);
            border-left: 4px solid #3b82f6;
        }

        .alert-box.success {
            background: linear-gradient(135deg, #dcfce7, #d1fae5);
            border-left: 4px solid #10b981;
        }

        .alert-box-title {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-box ul {
            margin-bottom: 0;
        }

        /* Contact Box */
        .contact-box {
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin: 25px 0;
        }

        .contact-box p {
            color: rgba(255,255,255,0.9);
            margin-bottom: 8px;
            text-align: left;
        }

        .contact-box strong {
            color: white;
        }

        .contact-box i {
            width: 25px;
            color: #fbbf24;
        }

        /* Table of Contents */
        .toc {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 25px 30px;
            margin-bottom: 35px;
        }

        .toc-title {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toc-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 30px;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .toc-list li {
            margin: 0;
        }

        .toc-list a {
            color: #3b82f6;
            text-decoration: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toc-list a:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        .toc-list a i {
            font-size: 0.7rem;
            color: #94a3b8;
        }

        /* Footer */
        .page-footer {
            text-align: center;
            padding: 30px 20px;
            color: #64748b;
            font-size: 0.9rem;
        }

        .page-footer p {
            text-align: center;
            margin-bottom: 0;
        }

        .page-footer a {
            color: #3b82f6;
            text-decoration: none;
        }

        /* Last Updated */
        .last-updated {
            text-align: left;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .last-updated i {
            color: #94a3b8;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
            margin-top: 15px;
        }

        .back-link:hover {
            color: #1d4ed8;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header {
                padding: 40px 20px;
            }

            .page-header h1 {
                font-size: 1.75rem;
            }

            .content-card {
                padding: 30px 20px;
                margin-top: -30px;
                border-radius: 16px;
            }

            h2 {
                font-size: 1.2rem;
            }

            .toc-list {
                grid-template-columns: 1fr;
            }

            .contact-box {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="page-header">
    <a href="<?php echo home_url(); ?>" class="logo">
        <i class="fas fa-rocket"></i>
        <?php bloginfo('name'); ?>
    </a>
    <h1><i class="fas fa-file-contract"></i> Điều khoản sử dụng</h1>
</header>

<!-- Content -->
<div class="container">
    <div class="content-card">

        <!-- Last Updated -->
        <p class="last-updated"><i class="fas fa-clock"></i> Cập nhật lần cuối: Tháng 12/2024</p>

        <!-- Table of Contents -->
        <div class="toc">
            <div class="toc-title">
                <i class="fas fa-list"></i> Mục lục
            </div>
            <ol class="toc-list">
                <li><a href="#gioi-thieu"><i class="fas fa-chevron-right"></i> Giới thiệu</a></li>
                <li><a href="#dinh-nghia"><i class="fas fa-chevron-right"></i> Định nghĩa</a></li>
                <li><a href="#dang-ky"><i class="fas fa-chevron-right"></i> Đăng ký tài khoản</a></li>
                <li><a href="#quy-dinh-user"><i class="fas fa-chevron-right"></i> Quy định cho User</a></li>
                <li><a href="#quy-dinh-customer"><i class="fas fa-chevron-right"></i> Quy định cho Khách hàng</a></li>
                <li><a href="#bao-mat"><i class="fas fa-chevron-right"></i> Chính sách bảo mật</a></li>
                <li><a href="#quyen-trach-nhiem"><i class="fas fa-chevron-right"></i> Quyền và trách nhiệm</a></li>
                <li><a href="#gioi-han"><i class="fas fa-chevron-right"></i> Giới hạn trách nhiệm</a></li>
                <li><a href="#xu-ly-vi-pham"><i class="fas fa-chevron-right"></i> Xử lý vi phạm</a></li>
                <li><a href="#tranh-chap"><i class="fas fa-chevron-right"></i> Giải quyết tranh chấp</a></li>
                <li><a href="#lien-he"><i class="fas fa-chevron-right"></i> Liên hệ</a></li>
                <li><a href="#ket-luan"><i class="fas fa-chevron-right"></i> Điều khoản cuối cùng</a></li>
            </ol>
        </div>

        <!-- 1. Giới thiệu -->
        <h2 id="gioi-thieu"><i class="fas fa-info-circle"></i> 1. Giới thiệu</h2>
        <p>Chào mừng bạn đến với <strong><?php bloginfo('name'); ?></strong> - nền tảng rút gọn link kiếm tiền hàng đầu Việt Nam. Chúng tôi kết nối người dùng (User) muốn kiếm tiền online và khách hàng (Customer) có nhu cầu quảng bá website, tăng traffic thực.</p>
        <p>Bằng việc truy cập và sử dụng website này, bạn đồng ý tuân thủ các điều khoản và điều kiện được nêu dưới đây. Nếu bạn không đồng ý với bất kỳ điều khoản nào, vui lòng không sử dụng dịch vụ của chúng tôi.</p>

        <!-- 2. Định nghĩa -->
        <h2 id="dinh-nghia"><i class="fas fa-book"></i> 2. Định nghĩa</h2>
        <ul>
            <li><strong>"Nền tảng"</strong>: Website <?php bloginfo('name'); ?> và tất cả các dịch vụ liên quan.</li>
            <li><strong>"Người dùng" (User)</strong>: Cá nhân đăng ký tài khoản để tạo shortlink, chia sẻ và kiếm tiền từ lượt truy cập.</li>
            <li><strong>"Khách hàng" (Customer)</strong>: Cá nhân hoặc tổ chức đăng ký để mua traffic, tạo chiến dịch quảng bá website.</li>
            <li><strong>"Shortlink"</strong>: Link rút gọn được tạo bởi User, chứa quảng cáo của Customer.</li>
            <li><strong>"View"</strong>: Lượt truy cập hợp lệ khi người xem hoàn thành các bước xác minh (captcha, countdown).</li>
            <li><strong>"Chiến dịch"</strong>: Gói quảng cáo do Customer tạo với URL đích, số lượng view mong muốn và ngân sách.</li>
        </ul>

        <!-- 3. Đăng ký tài khoản -->
        <h2 id="dang-ky"><i class="fas fa-user-plus"></i> 3. Đăng ký tài khoản</h2>

        <h3>3.1. Điều kiện đăng ký</h3>
        <ul>
            <li>Bạn phải từ <strong>18 tuổi trở lên</strong> hoặc có sự đồng ý của phụ huynh/người giám hộ.</li>
            <li>Cung cấp thông tin chính xác, đầy đủ khi đăng ký.</li>
            <li>Mỗi người chỉ được sở hữu <strong>01 tài khoản duy nhất</strong>.</li>
            <li>Số điện thoại và email phải là thật và thuộc quyền sở hữu của bạn.</li>
        </ul>

        <h3>3.2. Bảo mật tài khoản</h3>
        <ul>
            <li>Bạn chịu trách nhiệm bảo mật thông tin đăng nhập của mình.</li>
            <li>Không chia sẻ tài khoản cho người khác sử dụng.</li>
            <li>Thông báo ngay cho chúng tôi nếu phát hiện truy cập trái phép.</li>
        </ul>

        <!-- 4. Quy định cho User -->
        <h2 id="quy-dinh-user"><i class="fas fa-user"></i> 4. Quy định dành cho Người dùng (User)</h2>

        <h3>4.1. Tạo và chia sẻ Shortlink</h3>
        <ul>
            <li>User có thể tạo shortlink miễn phí từ các chiến dịch có sẵn.</li>
            <li>Chia sẻ shortlink trên các nền tảng hợp pháp: website cá nhân, mạng xã hội, forum, blog.</li>
            <li>Mỗi lượt view hợp lệ sẽ được tính tiền theo mức giá hiện hành.</li>
            <li>View được tính khi người xem hoàn thành <strong>tất cả các bước xác minh</strong> (captcha, đếm ngược).</li>
        </ul>

        <div class="alert-box danger">
            <div class="alert-box-title">
                <i class="fas fa-exclamation-triangle"></i> Hành vi bị cấm
            </div>
            <ul>
                <li>Tự click vào shortlink của mình hoặc nhờ người khác click giả</li>
                <li>Sử dụng bot, auto-click, traffic exchange để tăng view ảo</li>
                <li>Tạo nhiều tài khoản để nhận thưởng nhiều lần (multi-account)</li>
                <li>Sử dụng VPN, proxy, máy ảo để giả mạo IP</li>
                <li>Spam shortlink trên các nền tảng cấm quảng cáo</li>
                <li>Chia sẻ shortlink trên website có nội dung bất hợp pháp</li>
                <li>Lợi dụng lỗi hệ thống để trục lợi</li>
            </ul>
        </div>

        <h3>4.2. Phần thưởng và Rút tiền</h3>
        <ul>
            <li>Thu nhập được cộng <strong>tự động</strong> sau mỗi lượt view hợp lệ.</li>
            <li>Số dư tối thiểu để rút tiền: <strong>50.000đ</strong> (hoặc theo quy định hiện hành).</li>
            <li>Thời gian xử lý rút tiền: <strong>1-24 giờ</strong> trong ngày làm việc.</li>
            <li>Hỗ trợ rút tiền qua: <strong>Ngân hàng, USDT</strong>.</li>
            <li>Thông tin thanh toán phải chính xác và trùng khớp với tên chủ tài khoản.</li>
            <li>Chúng tôi có quyền tạm giữ hoặc từ chối rút tiền nếu phát hiện gian lận.</li>
        </ul>

        <!-- 5. Quy định cho Customer -->
        <h2 id="quy-dinh-customer"><i class="fas fa-building"></i> 5. Quy định dành cho Khách hàng (Customer)</h2>

        <h3>5.1. Tạo chiến dịch</h3>
        <ul>
            <li>Nạp tiền vào tài khoản trước khi tạo chiến dịch.</li>
            <li>Cung cấp <strong>URL đích hợp lệ</strong> - website phải hoạt động và không chứa malware.</li>
            <li>Chọn loại traffic phù hợp: Direct, Social, Search.</li>
            <li>Thiết lập số lượng view mong muốn và ngân sách cho chiến dịch.</li>
            <li>Chiến dịch sẽ được Admin duyệt trước khi hoạt động.</li>
        </ul>

        <div class="alert-box warning">
            <div class="alert-box-title">
                <i class="fas fa-ban"></i> Website bị cấm quảng cáo
            </div>
            <ul>
                <li>Nội dung khiêu dâm, người lớn (18+)</li>
                <li>Cờ bạc, cá độ trái phép</li>
                <li>Lừa đảo, scam, ponzi, đa cấp bất hợp pháp</li>
                <li>Virus, malware, phishing</li>
                <li>Vi phạm bản quyền, sở hữu trí tuệ</li>
                <li>Nội dung bạo lực, kích động hận thù</li>
                <li>Thuốc lá, chất cấm, vũ khí</li>
            </ul>
        </div>

        <h3>5.2. Thanh toán và Hoàn tiền</h3>
        <ul>
            <li>Nạp tiền qua: <strong>Chuyển khoản ngân hàng, USDT</strong>.</li>
            <li>Số dư được cộng sau khi Admin xác nhận thanh toán.</li>
            <li>Chi phí mỗi view được trừ tự động khi có lượt truy cập hợp lệ.</li>
            <li>Có thể yêu cầu hoàn tiền số dư chưa sử dụng (áp dụng phí xử lý 5-10%).</li>
            <li>Không hoàn tiền cho các view đã được ghi nhận.</li>
        </ul>

        <h3>5.3. Báo cáo và Thống kê</h3>
        <ul>
            <li>Xem thống kê real-time về lượt view, chi phí trong Dashboard.</li>
            <li>Báo cáo chi tiết theo ngày, tháng, chiến dịch.</li>
            <li>Hỗ trợ xuất báo cáo Excel để phân tích.</li>
        </ul>

        <!-- 6. Chính sách bảo mật -->
        <h2 id="bao-mat"><i class="fas fa-shield-alt"></i> 6. Chính sách bảo mật</h2>
        <p>Chúng tôi cam kết bảo vệ thông tin cá nhân của bạn:</p>
        <ul>
            <li>Thông tin cá nhân chỉ được sử dụng cho mục đích vận hành nền tảng.</li>
            <li>Không chia sẻ, bán thông tin cho bên thứ ba mà không có sự đồng ý.</li>
            <li>Áp dụng các biện pháp bảo mật: mã hóa SSL, xác thực 2 lớp.</li>
            <li>Lưu trữ log truy cập để phát hiện và ngăn chặn gian lận.</li>
            <li>Bạn có quyền yêu cầu xóa tài khoản và dữ liệu cá nhân.</li>
        </ul>

        <!-- 7. Quyền và trách nhiệm -->
        <h2 id="quyen-trach-nhiem"><i class="fas fa-balance-scale"></i> 7. Quyền và trách nhiệm của <?php bloginfo('name'); ?></h2>

        <h3>7.1. Quyền của chúng tôi</h3>
        <ul>
            <li>Thay đổi, cập nhật điều khoản sử dụng bất kỳ lúc nào.</li>
            <li>Điều chỉnh mức giá view, phí dịch vụ theo thị trường.</li>
            <li>Từ chối duyệt chiến dịch vi phạm quy định.</li>
            <li>Tạm ngưng hoặc chấm dứt tài khoản vi phạm mà không cần báo trước.</li>
            <li>Thu hồi thu nhập nếu phát hiện gian lận.</li>
            <li>Giới hạn số lượng view/ngày từ cùng một IP.</li>
        </ul>

        <h3>7.2. Trách nhiệm của chúng tôi</h3>
        <ul>
            <li>Cung cấp nền tảng hoạt động ổn định, an toàn 24/7.</li>
            <li>Đảm bảo tính chính xác của hệ thống đếm view.</li>
            <li>Xử lý yêu cầu rút tiền đúng thời hạn cam kết.</li>
            <li>Hỗ trợ giải quyết khiếu nại, tranh chấp một cách công bằng.</li>
            <li>Thông báo kịp thời khi có thay đổi quan trọng.</li>
        </ul>

        <!-- 8. Giới hạn trách nhiệm -->
        <h2 id="gioi-han"><i class="fas fa-exclamation-circle"></i> 8. Giới hạn trách nhiệm</h2>

        <div class="alert-box info">
            <div class="alert-box-title">
                <i class="fas fa-info-circle"></i> <?php bloginfo('name'); ?> không chịu trách nhiệm về:
            </div>
            <ul>
                <li>Thiệt hại do lỗi kỹ thuật, gián đoạn dịch vụ ngoài tầm kiểm soát.</li>
                <li>Mất mát do người dùng không bảo mật tài khoản.</li>
                <li>Nội dung, chất lượng website đích của khách hàng.</li>
                <li>View không được tính do người xem không hoàn thành xác minh.</li>
                <li>Tranh chấp giữa User và bên thứ ba.</li>
                <li>Thiệt hại do sử dụng shortlink sai mục đích.</li>
            </ul>
        </div>

        <!-- 9. Xử lý vi phạm -->
        <h2 id="xu-ly-vi-pham"><i class="fas fa-gavel"></i> 9. Xử lý vi phạm</h2>
        <p>Tùy theo mức độ vi phạm, chúng tôi có thể áp dụng các hình thức xử lý:</p>
        <ol>
            <li><strong>Cảnh cáo</strong>: Lần đầu vi phạm nhẹ.</li>
            <li><strong>Reset view ảo</strong>: Xóa các lượt view không hợp lệ.</li>
            <li><strong>Tạm khóa tài khoản</strong>: Vi phạm lặp lại hoặc nghiêm trọng (7-30 ngày).</li>
            <li><strong>Khóa vĩnh viễn</strong>: Vi phạm nghiêm trọng, gian lận có hệ thống.</li>
            <li><strong>Thu hồi số dư</strong>: Nếu số dư có được từ hành vi gian lận.</li>
            <li><strong>Block IP/Thiết bị</strong>: Ngăn chặn truy cập từ nguồn gian lận.</li>
        </ol>

        <!-- 10. Giải quyết tranh chấp -->
        <h2 id="tranh-chap"><i class="fas fa-handshake"></i> 10. Giải quyết tranh chấp</h2>
        <ul>
            <li>Mọi tranh chấp sẽ được giải quyết thông qua <strong>hệ thống ticket hỗ trợ</strong>.</li>
            <li>Cung cấp đầy đủ bằng chứng khi khiếu nại (screenshot, video, log).</li>
            <li>Thời gian xử lý khiếu nại: <strong>1-7 ngày làm việc</strong>.</li>
            <li>Quyết định của Admin là quyết định cuối cùng.</li>
            <li>Nếu không thể thương lượng, tranh chấp sẽ được giải quyết tại Tòa án có thẩm quyền tại Việt Nam.</li>
        </ul>

        <!-- 11. Liên hệ -->
        <h2 id="lien-he"><i class="fas fa-envelope"></i> 11. Liên hệ</h2>
        <p>Nếu bạn có bất kỳ câu hỏi nào về Điều khoản sử dụng, vui lòng liên hệ:</p>

        <div class="contact-box">
            <?php if ( $email ): ?>
            <p><i class="fas fa-envelope"></i> <strong>Email:</strong> <?php echo esc_html( $email ); ?></p>
            <?php endif; ?>
            <?php if ( $telegram ): ?>
            <p><i class="fab fa-telegram"></i> <strong>Telegram:</strong> <?php echo esc_html( $telegram ); ?></p>
            <?php endif; ?>
            <?php if ( $signal ): ?>
            <p><i class="fas fa-comment-dots"></i> <strong>Signal:</strong> Liên hệ qua Signal</p>
            <?php endif; ?>
            <?php if ( $zalo ): ?>
            <p><i class="fas fa-comment"></i> <strong>Zalo:</strong> <?php echo esc_html( $zalo ); ?></p>
            <?php endif; ?>
            <p><i class="fas fa-globe"></i> <strong>Website:</strong> <?php echo home_url(); ?></p>
        </div>

        <!-- 12. Điều khoản cuối cùng -->
        <h2 id="ket-luan"><i class="fas fa-check-circle"></i> 12. Điều khoản cuối cùng</h2>
        <p>Bằng việc nhấn <strong>"Đăng ký"</strong> hoặc tiếp tục sử dụng dịch vụ, bạn xác nhận đã đọc, hiểu và đồng ý với toàn bộ các điều khoản trên.</p>
        <p>Chúng tôi có quyền cập nhật điều khoản này bất kỳ lúc nào. Phiên bản mới sẽ có hiệu lực ngay khi được đăng tải trên website.</p>
        <p><strong>Cập nhật lần cuối:</strong> <?php echo date('d/m/Y', strtotime(taskify_current_time())); ?></p>

        <div class="alert-box success">
            <div class="alert-box-title">
                <i class="fas fa-heart"></i> Cảm ơn bạn đã tin tưởng sử dụng <?php bloginfo('name'); ?>!
            </div>
            <p style="margin-bottom: 0;">Chúng tôi cam kết mang đến nền tảng rút gọn link uy tín, thanh toán nhanh chóng và hỗ trợ tận tình.</p>
        </div>

    </div>
</div>

<!-- Footer -->
<footer class="page-footer">
    <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?> - All rights reserved.</p>
    <a href="<?php echo home_url(); ?>" class="back-link">
        <i class="fas fa-arrow-left"></i> Quay về trang chủ
    </a>
</footer>

<?php wp_footer(); ?>
</body>
</html>
