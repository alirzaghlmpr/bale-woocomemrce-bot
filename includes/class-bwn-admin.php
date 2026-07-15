<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWN_Admin {

    /**
     * Hook administration screens actions.
     */
    public static function init() {
        add_action( 'admin_menu',            array( __CLASS__, 'add_admin_menu' ) );
        add_action( 'admin_init',            array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
    }

    /**
     * Add settings page inside WooCommerce menu context.
     */
    public static function add_admin_menu() {
        add_menu_page( 
            'بله نوتیفایر', 
            'بله نوتیفایر', 
            'manage_options',
            'bale-woo-notifier', 
            array( __CLASS__, 'render_settings_page' ), 
            'dashicons-bell', 
            56 
        );
    }

    /**
     * Register settings.
     */
    public static function register_settings() {
        register_setting( 'bwn_settings_group', BWN_OPTIONS_KEY, array( __CLASS__, 'sanitize_settings' ) );
    }

    /**
     * Sanitize saved settings data.
     */
    public static function sanitize_settings( $input ) {
        return array(
            'bot_token'        => sanitize_text_field( $input['bot_token'] ?? '' ),
            'chat_id'          => sanitize_text_field( $input['chat_id']   ?? '' ),
            'message_template' => isset( $input['message_template'] ) ? wp_kses_post( $input['message_template'] ) : '',
        );
    }

    /**
     * Safe option retrieval wrapper.
     */
    public static function get_option( $key, $default = '' ) {
        $opts = get_option( BWN_OPTIONS_KEY, array() );
        return $opts[ $key ] ?? $default;
    }

    /**
     * Enqueue CSS styling for settings page.
     */
    public static function enqueue_styles( $hook ) {
        if ( $hook !== 'toplevel_page_bale-woo-notifier' ) {
            return;
        }
        
        wp_add_inline_style( 'wp-admin', '
            .bwn-wrap{max-width:1140px;direction:rtl;font-family:Tahoma,sans-serif;margin:20px auto 0 auto;padding-left:20px}
            .bwn-wrap h1{display:flex;align-items:center;gap:12px;margin-bottom:20px;font-size:22px;color:#1d2327}
            .bwn-grid{display:grid;grid-template-columns:1fr;gap:24px}
            @media (min-width: 900px) {
                .bwn-grid{grid-template-columns: 3fr 2fr;}
            }
            .bwn-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:24px 28px;margin-bottom:24px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05),0 2px 4px -1px rgba(0,0,0,0.03);position:relative;overflow:hidden}
            .bwn-card h2{margin-top:0;font-size:17px;color:#1f2937;border-bottom:2px solid #3b82f6;padding-bottom:12px;margin-bottom:20px}
            .bwn-field{margin-bottom:20px}
            .bwn-field label{display:block;font-weight:600;margin-bottom:8px;color:#374151;font-size:13px}
            .bwn-field input[type=text],.bwn-field input[type=password],.bwn-field textarea{width:100%;padding:10px 14px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;box-shadow:0 1px 2px 0 rgba(0,0,0,0.05);transition:border-color 0.2s}
            .bwn-field input[type=text]:focus,.bwn-field input[type=password]:focus,.bwn-field textarea:focus{border-color:#3b82f6;outline:0;box-shadow:0 0 0 3px rgba(59,130,246,0.15)}
            .bwn-field textarea{font-family:Consolas, Monaco, monospace;direction:ltr;text-align:left}
            .bwn-field .description{color:#6b7280;font-size:12px;margin-top:6px;line-height:1.5}
            .bwn-test-btn{background:#3b82f6;color:#fff;border:none;padding:10px 20px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:500;transition:background 0.2s}
            .bwn-test-btn:hover{background:#2563eb}
            .bwn-badge{background:#3b82f6;color:#fff;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:bold}
            .bwn-status{margin-top:10px;padding:12px 16px;border-radius:6px;display:none;font-weight:500}
            .bwn-status.success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;display:block}
            .bwn-status.error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca;display:block}
            .bwn-log-table{width:100%;border-collapse:collapse;font-size:13px;direction:rtl}
            .bwn-log-table th{background:#f9fafb;padding:10px 14px;text-align:right;border:1px solid #e5e7eb;color:#374151;font-weight:600}
            .bwn-log-table td{padding:10px 14px;border:1px solid #e5e7eb;vertical-align:top;color:#4b5563}
            .bwn-log-table tr:nth-child(even) td{background:#f9fafb}
            .status-ok{color:#059669;font-weight:bold}
            .status-fail{color:#dc2626;font-weight:bold}
            
            /* Variables Styling */
            .bwn-vars-list{display:flex;flex-direction:column;gap:10px;max-height:450px;overflow-y:auto;padding-left:5px}
            .bwn-var-item{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 10px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;transition:background 0.2s}
            .bwn-var-item:hover{background:#f3f4f6}
            .bwn-var-badge{background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;padding:4px 10px;border-radius:6px;font-family:Consolas, Monaco, monospace;font-size:12px;font-weight:bold;cursor:pointer;user-select:none;transition:all 0.2s;direction:ltr;text-align:left}
            .bwn-var-badge:hover{background:#bae6fd;transform:scale(1.03)}
            .bwn-var-badge.copied{background:#dcfce7 !important;color:#15803d !important;border-color:#bbf7d0 !important}
            .bwn-var-badge.bwn-meta-badge{background:#f3e8ff;color:#6b21a8;border-color:#e9d5ff}
            .bwn-var-badge.bwn-meta-badge:hover{background:#e9d5ff}
            .bwn-var-details{display:flex;flex-direction:column;align-items:flex-end;flex-grow:1;max-width:60%}
            .bwn-var-label{font-size:12px;font-weight:600;color:#374151}
            .bwn-var-value{font-size:11px;color:#9ca3af;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;width:100%;text-align:left;direction:ltr}
        ' );
    }

    /**
     * Get last completed order for placeholder values.
     */
    public static function get_last_completed_order() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return null;
        }
        
        $orders = wc_get_orders( array(
            'limit'   => 1,
            'status'  => 'completed',
            'orderby' => 'date',
            'order'   => 'DESC',
        ) );
        
        if ( empty( $orders ) ) {
            $orders = wc_get_orders( array(
                'limit'   => 1,
                'orderby' => 'date',
                'order'   => 'DESC',
            ) );
        }
        
        return ! empty( $orders ) ? $orders[0] : null;
    }

    /**
     * Dispatch test notification.
     */
    public static function send_test_message() {
        $last_order = self::get_last_completed_order();
        if ( $last_order ) {
            $msg = BWN_Notifier::build_message( $last_order );
        } else {
            $msg = self::build_mock_message();
        }
        return BWN_Notifier::send_to_bale( $msg );
    }

    /**
     * Fallback mock message when no store orders exist.
     */
    public static function build_mock_message() {
        $today = BWN_Jalali::today();

        $msg  = "هدلایت M125 Pro برند LENZO پایه H1 / H4 / H7 / 9005 پایه H4\n";
        $msg .= "33,500,000 ریال × 1\n\n";
        $msg .= "دیسک ترمز جلو (اسپرت) سوراخدار 206 تیپ ۵ - رانا - ۲۰۷ - پارس tu5 برند KHF\n";
        $msg .= "48,000,000 ریال × 1\n\n";
        $msg .= "منبع سفارش : گوگل\n\n";
        $msg .= "روش ارسال : تیپاکس (پس کرایه)\n\n";
        $msg .= "مجموع سبد خرید : 81,500,000 ریال\n";
        $msg .= "هزینه ارسال : 0 ریال\n\n";
        $msg .= "مجموع : 81,500,000 ریال\n";
        $msg .= "{$today}\n\n";
        $msg .= "پارسا بابایی\n";
        $msg .= "البرز\n";
        $msg .= "کرج\n";
        $msg .= "کرج گلشهر شرقی بلوار ایثار (درختی) بن بست داودی پلاک ۱۶ فست فود سیب ۳۶۰\n";
        $msg .= "3137865451\n\n";
        $msg .= "آدرس ایمیل:\n";
        $msg .= "parsababaee76@gmail.com\n\n";
        $msg .= "تلفن:\n";
        $msg .= "09193765429\n\n";
        $msg .= "توضیحات : \n\n\n";
        $msg .= "شماره سفارش : 22884\n\n";
        $msg .= "لینک محصولات :\n\n";
        $msg .= "دیسک ترمز جلو (اسپرت) سوراخدار 206 تیپ ۵ - رانا - ۲۰۷ - پارس tu5 برند KHF\n";
        $msg .= "https://sport-formol1.com/product/sample-brake-disc\n\n";
        $msg .= "هدلایت M125 Pro برند LENZO پایه H1 / H4 / H7 / 9005\n";
        $msg .= "https://sport-formol1.com/product/sample-headlight\n";

        return $msg;
    }

    /**
     * Render the admin configuration screen.
     */
    public static function render_settings_page() {
        $test_result = null;
        if ( isset( $_POST['bwn_test_nonce'] ) && wp_verify_nonce( $_POST['bwn_test_nonce'], 'bwn_test_message' ) ) {
            $test_result = self::send_test_message();
        }
        
        $last_order   = self::get_last_completed_order();
        $placeholders = BWN_Notifier::get_available_placeholders( $last_order );
        $meta_vars    = BWN_Notifier::get_order_meta_variables( $last_order );
        $log          = BWN_Notifier::get_log();

        $payment_placeholders     = array();
        $attribution_placeholders = array();
        $order_placeholders       = array();
        foreach ( $placeholders as $key => $data ) {
            if ( strpos( $key, 'payment_' ) === 0 ) {
                $payment_placeholders[ $key ] = $data;
            } elseif ( strpos( $key, 'attribution_' ) === 0 ) {
                $attribution_placeholders[ $key ] = $data;
            } else {
                $order_placeholders[ $key ] = $data;
            }
        }
        ?>
        <div class="bwn-wrap wrap">
            <h1>🔔 بله نوتیفایر <span class="bwn-badge">WooCommerce</span></h1>

            <?php if ( $test_result !== null ) : ?>
                <div class="bwn-status <?php echo $test_result['success'] ? 'success' : 'error'; ?>">
                    <?php echo esc_html( $test_result['success'] ? '✅ پیام تست با موفقیت ارسال شد!' : '❌ خطا: ' . ( $test_result['error'] ?? '' ) ); ?>
                </div>
            <?php endif; ?>

            <div class="bwn-grid">
                <!-- Left Column: Main Settings Form & Info -->
                <div class="bwn-col-main">
                    <div class="bwn-card">
                        <h2>⚙️ تنظیمات ربات بله</h2>
                        <form method="post" action="options.php">
                            <?php settings_fields( 'bwn_settings_group' ); ?>
                            <div class="bwn-field">
                                <label>توکن ربات (Bot Token)</label>
                                <input type="password" name="<?php echo BWN_OPTIONS_KEY; ?>[bot_token]"
                                       value="<?php echo esc_attr( self::get_option('bot_token') ); ?>"
                                       placeholder="123456789:ABCDefgh..." />
                                <div class="description">توکن را از @BotFather در بله دریافت کنید.</div>
                            </div>
                            <div class="bwn-field">
                                <label>شناسه گروه (Chat ID)</label>
                                <input type="text" name="<?php echo BWN_OPTIONS_KEY; ?>[chat_id]"
                                       value="<?php echo esc_attr( self::get_option('chat_id') ); ?>"
                                       placeholder="-1001234567890" />
                                <div class="description">شناسه گروهی که ربات عضو آن است (معمولاً با منفی شروع می‌شود).</div>
                            </div>
                            <div class="bwn-field">
                                <label>قالب پیام ارسالی (Message Template)</label>
                                <textarea name="<?php echo BWN_OPTIONS_KEY; ?>[message_template]" rows="15"
                                          placeholder="متن خود را با متغیرها اینجا بنویسید..."><?php echo esc_textarea( self::get_option('message_template', BWN_Notifier::get_default_message_template()) ); ?></textarea>
                                <div class="description" style="direction:rtl;text-align:right;">
                                    می‌توانید از متغیرهای ستون سمت چپ در پیام استفاده کنید. در صورتی که این فیلد خالی بماند، از قالب پیش‌فرض استفاده خواهد شد.
                                </div>
                            </div>
                            <?php submit_button( 'ذخیره تنظیمات', 'primary', 'submit', false ); ?>
                        </form>
                    </div>

                    <div class="bwn-card">
                        <h2>🧪 ارسال پیام تست</h2>
                        <form method="post">
                            <?php wp_nonce_field( 'bwn_test_message', 'bwn_test_nonce' ); ?>
                            <p style="color:#555;font-size:13px;margin-bottom:15px;line-height:1.5;">یک پیام نمونه براساس آخرین سفارش تکمیل شده به گروه ارسال می‌کند تا اتصال را تأیید کنید.</p>
                            <button type="submit" class="bwn-test-btn">📤 ارسال پیام تست</button>
                        </form>
                    </div>

                    <div class="bwn-card">
                        <h2>📋 آخرین ارسال‌ها</h2>
                        <?php if ( empty( $log ) ) : ?>
                            <p style="color:#888;">هنوز هیچ سفارشی ارسال نشده است.</p>
                        <?php else : ?>
                            <table class="bwn-log-table">
                                <thead><tr>
                                    <th>تاریخ</th><th>شماره سفارش</th><th>وضعیت</th><th>پیام خطا</th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ( array_reverse( $log ) as $entry ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( $entry['time'] ); ?></td>
                                        <td>#<?php echo esc_html( $entry['order_id'] ); ?></td>
                                        <td class="<?php echo $entry['success'] ? 'status-ok' : 'status-fail'; ?>">
                                            <?php echo $entry['success'] ? '✅ ارسال شد' : '❌ خطا'; ?>
                                        </td>
                                        <td style="color:#c00;font-size:12px;"><?php echo esc_html( $entry['error'] ?? '' ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column: Interactive Placeholders list -->
                <div class="bwn-col-sidebar">
                    <div class="bwn-card">
                        <h2>📋 متغیرهای قابل استفاده</h2>
                        <p style="font-size:12px;color:#666;margin-bottom:20px;line-height:1.6;">
                            می‌توانید با کلیک روی هر متغیر، آن را کپی کرده و در قالب پیام خود استفاده کنید.
                            <br/><br/>
                            <?php if ( $last_order ) : ?>
                                <span style="color:#0284c7;font-weight:600;">📍 مقادیر پیش‌نمایش زیر بر اساس آخرین سفارش تکمیل شده (شماره #<?php echo esc_html( $last_order->get_id() ); ?>) بارگذاری شده‌اند.</span>
                            <?php else : ?>
                                <span style="color:#e11d48;font-weight:600;">⚠️ سفارشی در سیستم یافت نشد. مقادیر پیش‌نمایش به صورت نمونه هستند.</span>
                            <?php endif; ?>
                        </p>

                        <h3 style="font-size:14px;color:#1f2937;margin-top:15px;margin-bottom:12px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;">🗂️ متغیرهای استاندارد سفارش</h3>
                        <div class="bwn-vars-list">
                            <?php foreach ( $order_placeholders as $key => $data ) : ?>
                                <div class="bwn-var-item">
                                    <div class="bwn-var-badge bwn-copyable" data-copy="<?php echo esc_attr( '{' . $key . '}' ); ?>" title="کلیک برای کپی">
                                        <?php echo esc_html( '{' . $key . '}' ); ?>
                                    </div>
                                    <div class="bwn-var-details">
                                        <span class="bwn-var-label"><?php echo esc_html( $data['label'] ); ?></span>
                                        <span class="bwn-var-value" title="<?php echo esc_attr( $data['val'] ); ?>">
                                            <?php echo esc_html( wp_trim_words( $data['val'], 8, '...' ) ?: '-' ); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <h3 style="font-size:14px;color:#1f2937;margin-top:30px;margin-bottom:12px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;">💳 درگاه پرداخت</h3>
                        <div class="bwn-vars-list">
                            <?php foreach ( $payment_placeholders as $key => $data ) : ?>
                                <div class="bwn-var-item">
                                    <div class="bwn-var-badge bwn-copyable" data-copy="<?php echo esc_attr( '{' . $key . '}' ); ?>" title="کلیک برای کپی">
                                        <?php echo esc_html( '{' . $key . '}' ); ?>
                                    </div>
                                    <div class="bwn-var-details">
                                        <span class="bwn-var-label"><?php echo esc_html( $data['label'] ); ?></span>
                                        <span class="bwn-var-value" title="<?php echo esc_attr( $data['val'] ); ?>">
                                            <?php echo esc_html( wp_trim_words( $data['val'], 8, '...' ) ?: '-' ); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <h3 style="font-size:14px;color:#1f2937;margin-top:30px;margin-bottom:12px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;">📈 اتریبیوشن سفارش (منبع ورودی)</h3>
                        <p style="font-size:11px;color:#6b7280;margin-bottom:12px;line-height:1.5;">داده‌های ردیابی منبع سفارش که توسط خود ووکامرس (Order Attribution) ثبت می‌شود.</p>
                        <div class="bwn-vars-list">
                            <?php foreach ( $attribution_placeholders as $key => $data ) : ?>
                                <div class="bwn-var-item">
                                    <div class="bwn-var-badge bwn-copyable" data-copy="<?php echo esc_attr( '{' . $key . '}' ); ?>" title="کلیک برای کپی">
                                        <?php echo esc_html( '{' . $key . '}' ); ?>
                                    </div>
                                    <div class="bwn-var-details">
                                        <span class="bwn-var-label"><?php echo esc_html( $data['label'] ); ?></span>
                                        <span class="bwn-var-value" title="<?php echo esc_attr( $data['val'] ); ?>">
                                            <?php echo esc_html( wp_trim_words( $data['val'], 8, '...' ) ?: '-' ); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <h3 style="font-size:14px;color:#1f2937;margin-top:30px;margin-bottom:12px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;">🧩 متای سفارشی (Custom Order Meta)</h3>
                        <?php if ( empty( $meta_vars ) ) : ?>
                            <p style="font-size:12px;color:#9ca3af;font-style:italic;">هیچ متای سفارشی در آخرین سفارش یافت نشد.</p>
                        <?php else : ?>
                            <p style="font-size:11px;color:#6b7280;margin-bottom:12px;line-height:1.5;">فیلدهای متای اختصاصی که توسط کدهای دیگر یا افزونه‌ها به سفارشات شما اضافه شده‌اند:</p>
                            <div class="bwn-vars-list">
                                <?php foreach ( $meta_vars as $key => $val ) : ?>
                                    <div class="bwn-var-item">
                                        <div class="bwn-var-badge bwn-meta-badge bwn-copyable" data-copy="<?php echo esc_attr( '{meta:' . $key . '}' ); ?>" title="کلیک برای کپی">
                                            <?php echo esc_html( '{meta:' . $key . '}' ); ?>
                                        </div>
                                        <div class="bwn-var-details">
                                            <span class="bwn-var-label" style="direction:ltr;unicode-bidi:embed;text-align:right;font-size:11px;color:#4b5563;"><?php echo esc_html( $key ); ?></span>
                                            <span class="bwn-var-value" title="<?php echo esc_attr( $val ); ?>">
                                                <?php echo esc_html( wp_trim_words( $val, 8, '...' ) ?: '-' ); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <p style="color:#aaa;font-size:11px;margin-top:20px;text-align:center;">Bale WooCommerce Notifier v<?php echo BWN_VERSION; ?></p>
        </div>

        <script>
        document.querySelectorAll('.bwn-copyable').forEach(function(el) {
            el.addEventListener('click', function() {
                var text = this.getAttribute('data-copy');
                var originalText = el.innerHTML;
                
                navigator.clipboard.writeText(text).then(function() {
                    el.innerHTML = 'کپی شد!';
                    el.classList.add('copied');
                    setTimeout(function() {
                        el.innerHTML = originalText;
                        el.classList.remove('copied');
                    }, 1000);
                }).catch(function(err) {
                    var textarea = document.createElement('textarea');
                    textarea.value = text;
                    document.body.appendChild(textarea);
                    textarea.select();
                    try {
                        document.execCommand('copy');
                        el.innerHTML = 'کپی شد!';
                        el.classList.add('copied');
                        setTimeout(function() {
                            el.innerHTML = originalText;
                            el.classList.remove('copied');
                        }, 1000);
                    } catch (e) {}
                    document.body.removeChild(textarea);
                });
            });
        });
        </script>
        <?php
    }
}
