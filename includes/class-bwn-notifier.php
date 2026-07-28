<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWN_Notifier {
    
    /**
     * Hook the notifier actions.
     */
    public static function init() {
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'send_order_notification' ), 10, 2 );
    }

    /**
     * Send order details to Bale on order status processing.
     */
    public static function send_order_notification( $order_id, $order = null ) {
        if ( ! $order ) {
            $order = wc_get_order( $order_id );
        }
        if ( ! $order ) {
            return;
        }

        $message   = self::build_message( $order );
        $photo_url = self::get_receipt_photo_url( $order );

        if ( $photo_url ) {
            $result = self::send_photo( $photo_url, $message );
            if ( ! $result['success'] ) {
                // Bale couldn't fetch/accept the receipt image (unreachable
                // URL, format, size, ...) — the order details still matter
                // more than the receipt, so fall back to the plain text
                // message instead of silently sending nothing.
                $result = self::send_to_bale( $message );
            }
        } else {
            $result = self::send_to_bale( $message );
        }

        self::log_entry( $order_id, $result['success'], $result['error'] ?? '' );
    }

    /**
     * The receipt photo only gets sent for orders created via the Payment
     * Link tool (admin-recorded manual/Instagram sale, `_wpl_created_by`
     * meta — see inc/payment-link/includes/class-order-handler.php in the
     * theme) and only if one was actually attached via the existing
     * "ضمیمه سفارش" metabox (`_order_image`, class-order-metabox.php) —
     * that field isn't necessarily a receipt on a normal online order, so
     * it's not treated as one there.
     */
    private static function get_receipt_photo_url( WC_Order $order ): string {
        if ( ! self::is_manual_order( $order ) ) {
            return '';
        }
        $image_id = (int) $order->get_meta( '_order_image' );
        return $image_id ? (string) wp_get_attachment_url( $image_id ) : '';
    }

    /** Whether $order was created by an admin via the Payment Link tool rather than the normal storefront checkout. */
    public static function is_manual_order( WC_Order $order ): bool {
        return (bool) $order->get_meta( '_wpl_created_by' );
    }

    /**
     * Send raw message via Bale Bot API.
     */
    public static function send_to_bale( $text ) {
        $token   = BWN_Admin::get_option( 'bot_token' );
        $chat_id = BWN_Admin::get_option( 'chat_id' );

        if ( empty( $token ) || empty( $chat_id ) ) {
            return array( 'success' => false, 'error' => 'توکن یا Chat ID تنظیم نشده است.' );
        }

        $response = wp_remote_post(
            BWN_BALE_API . $token . '/sendMessage',
            array(
                'body'        => wp_json_encode( array(
                    'chat_id'    => $chat_id,
                    'text'       => $text,
                    'parse_mode' => 'HTML',
                ) ),
                'headers'     => array( 'Content-Type' => 'application/json' ),
                'timeout'     => 15,
                'data_format' => 'body',
            )
        );

        if ( is_wp_error( $response ) ) {
            return array( 'success' => false, 'error' => $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $json = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 && ! empty( $json['ok'] ) ) {
            return array( 'success' => true );
        }

        return array( 'success' => false, 'error' => $json['description'] ?? "HTTP {$code}" );
    }

    /**
     * Send a photo via Bale Bot API's sendPhoto, by URL — per docs.bale.ai,
     * `photo` accepts a plain HTTP(S) URL (Bale fetches it server-side,
     * ≤5MB) alongside file_id/multipart, so the existing WP attachment URL
     * from _order_image needs no re-upload. No `parse_mode` here (unlike
     * sendMessage) — it isn't a documented sendPhoto parameter.
     */
    public static function send_photo( $photo_url, $caption ) {
        $token   = BWN_Admin::get_option( 'bot_token' );
        $chat_id = BWN_Admin::get_option( 'chat_id' );

        if ( empty( $token ) || empty( $chat_id ) ) {
            return array( 'success' => false, 'error' => 'توکن یا Chat ID تنظیم نشده است.' );
        }

        // Bale's documented caption limit is 4096 characters, same as sendMessage's text limit.
        if ( function_exists( 'mb_substr' ) && mb_strlen( $caption ) > 4096 ) {
            $caption = mb_substr( $caption, 0, 4096 );
        }

        $response = wp_remote_post(
            BWN_BALE_API . $token . '/sendPhoto',
            array(
                'body'        => wp_json_encode( array(
                    'chat_id' => $chat_id,
                    'photo'   => $photo_url,
                    'caption' => $caption,
                ) ),
                'headers'     => array( 'Content-Type' => 'application/json' ),
                'timeout'     => 20,
                'data_format' => 'body',
            )
        );

        if ( is_wp_error( $response ) ) {
            return array( 'success' => false, 'error' => $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $json = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 && ! empty( $json['ok'] ) ) {
            return array( 'success' => true );
        }

        return array( 'success' => false, 'error' => $json['description'] ?? "HTTP {$code}" );
    }

    /**
     * Build message text by parsing placeholders and templates.
     */
    public static function build_message( WC_Order $order ) {
        // Auto-selected per order (not a per-request toggle an admin has to
        // remember) — every call site (real dispatch, the settings page's
        // test button, anything else that calls build_message()) picks the
        // right template for free just by which order gets passed in.
        $is_manual   = self::is_manual_order( $order );
        $option_key  = $is_manual ? 'message_template_manual' : 'message_template';
        $template    = BWN_Admin::get_option( $option_key, '' );

        if ( empty( trim( $template ) ) ) {
            $template = $is_manual ? self::get_default_manual_message_template() : self::get_default_message_template();
        }

        $placeholders = self::get_available_placeholders( $order );
        $msg = $template;
        
        // Replace standard placeholders
        foreach ( $placeholders as $key => $data ) {
            $msg = str_replace( '{' . $key . '}', $data['val'], $msg );
        }
        
        // Replace custom meta placeholders: {meta:some_key}
        $msg = preg_replace_callback( '/\{meta:([a-zA-Z0-9_\-\:]+)\}/', function( $matches ) use ( $order ) {
            $meta_key = $matches[1];
            $val = $order->get_meta( $meta_key );
            if ( is_array( $val ) || is_object( $val ) ) {
                return wp_json_encode( $val );
            }
            return (string) $val;
        }, $msg );
        
        // Clean up excessive newlines
        $msg = preg_replace( "/\n{3,}/", "\n\n", $msg );
        
        return trim( $msg );
    }

    /**
     * Get default notification template.
     */
    public static function get_default_message_template() {
        return "{order_items}

منبع سفارش : {order_source}

روش ارسال : {shipping_method}

مجموع سبد خرید : {order_subtotal} {currency_symbol}
هزینه ارسال : {order_shipping} {currency_symbol}

مجموع : {order_total} {currency_symbol}
{order_date}

{customer_name}
{billing_state}
{billing_city}
{billing_address}
{billing_postcode}

آدرس ایمیل:
{customer_email}

تلفن:
{customer_phone}

توضیحات :
{customer_note}

شماره سفارش : {order_id}

لینک محصولات :

{product_links}";
    }

    /**
     * Default template for orders created by an admin via the Payment Link
     * tool (a real sale that happened elsewhere — e.g. Instagram — recorded
     * on the site afterward, not paid through the online gateway). Freely
     * editable from the settings screen same as the normal template; this
     * is just a sensible starting point.
     */
    public static function get_default_manual_message_template() {
        return "📌 فاکتور دستی (ثبت‌شده توسط ادمین)

{order_items}

مجموع سبد خرید : {order_subtotal} {currency_symbol}
هزینه ارسال : {order_shipping} {currency_symbol}

مجموع : {order_total} {currency_symbol}
{order_date}

{customer_name}
{billing_state}
{billing_city}
{billing_address}
{billing_postcode}

تلفن:
{customer_phone}

شماره سفارش : {order_id}

رسید پرداخت : {receipt_url}";
    }

    /**
     * Map order values to standard placeholders.
     */
    public static function get_available_placeholders( $order ) {
        $placeholders = array(
            'order_id'        => array( 'label' => 'شناسه سفارش', 'val' => '' ),
            'order_number'    => array( 'label' => 'شماره سفارش', 'val' => '' ),
            'order_status'    => array( 'label' => 'وضعیت سفارش', 'val' => '' ),
            'order_date'      => array( 'label' => 'تاریخ سفارش (شمسی)', 'val' => '' ),
            'currency_symbol' => array( 'label' => 'نماد ارز', 'val' => '' ),
            'order_items'     => array( 'label' => 'اقلام سفارش', 'val' => '' ),
            'product_links'   => array( 'label' => 'لینک محصولات', 'val' => '' ),
            'order_source'    => array( 'label' => 'منبع سفارش', 'val' => '' ),
            'shipping_method' => array( 'label' => 'روش ارسال', 'val' => '' ),
            'order_subtotal'  => array( 'label' => 'مجموع سبد خرید', 'val' => '' ),
            'order_shipping'  => array( 'label' => 'هزینه ارسال', 'val' => '' ),
            'order_total'     => array( 'label' => 'مجموع نهایی', 'val' => '' ),
            'customer_name'   => array( 'label' => 'نام خریدار', 'val' => '' ),
            'customer_phone'  => array( 'label' => 'تلفن خریدار', 'val' => '' ),
            'customer_email'  => array( 'label' => 'ایمیل خریدار', 'val' => '' ),
            'billing_state'   => array( 'label' => 'استان خریدار', 'val' => '' ),
            'billing_city'    => array( 'label' => 'شهر خریدار', 'val' => '' ),
            'billing_address' => array( 'label' => 'آدرس خریدار', 'val' => '' ),
            'billing_postcode'=> array( 'label' => 'کد پستی خریدار', 'val' => '' ),
            'customer_note'   => array( 'label' => 'توضیحات سفارش', 'val' => '' ),

            'is_manual_order' => array( 'label' => 'ثبت‌شده توسط ادمین؟ (بله/خیر)', 'val' => '' ),
            'receipt_url'     => array( 'label' => 'لینک تصویر رسید (ضمیمه سفارش)', 'val' => '' ),

            'payment_method'       => array( 'label' => 'شناسه درگاه پرداخت', 'val' => '' ),
            'payment_method_title' => array( 'label' => 'نام درگاه پرداخت', 'val' => '' ),
            'payment_transaction_id' => array( 'label' => 'کد رهگیری / هش تراکنش', 'val' => '' ),
        );

        $placeholders = array_merge( $placeholders, self::get_attribution_placeholder_defs() );

        if ( $order ) {
            $currency = get_woocommerce_currency_symbol();
            
            $placeholders['order_id']['val']        = $order->get_id();
            $placeholders['order_number']['val']    = $order->get_order_number();
            $placeholders['order_status']['val']    = $order->get_status();
            $placeholders['order_date']['val']      = BWN_Jalali::order_date( $order );
            $placeholders['currency_symbol']['val'] = $currency;
            
            $items_text = '';
            $links_text = '';
            foreach ( $order->get_items() as $item ) {
                $product  = $item->get_product();
                $name     = $item->get_name();
                $qty      = $item->get_quantity();
                $price    = number_format( (float) $order->get_item_subtotal( $item, false, true ) );
                $url      = $product ? get_permalink( $product->get_id() ) : '';

                $items_text .= "{$name}\n{$price} {$currency} × {$qty}\n\n";
                if ( $url ) $links_text .= "{$name}\n{$url}\n\n";
            }
            $placeholders['order_items']['val']   = trim( $items_text );
            $placeholders['product_links']['val'] = trim( $links_text );
            
            $placeholders['order_source']['val']    = self::get_order_source( $order );
            $placeholders['shipping_method']['val'] = self::get_shipping_method( $order );
            $placeholders['order_subtotal']['val']  = number_format( (float) $order->get_subtotal() );
            $placeholders['order_shipping']['val']  = number_format( (float) $order->get_shipping_total() );
            $placeholders['order_total']['val']     = number_format( (float) $order->get_total() );
            
            $placeholders['customer_name']['val']   = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
            $placeholders['customer_phone']['val']  = $order->get_billing_phone();
            $placeholders['customer_email']['val']  = $order->get_billing_email();
            $placeholders['billing_state']['val']   = $order->get_billing_state();
            $placeholders['billing_city']['val']    = $order->get_billing_city();
            $placeholders['billing_address']['val'] = trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() );
            $placeholders['billing_postcode']['val']= $order->get_billing_postcode();
            $placeholders['customer_note']['val']   = $order->get_customer_note();

            $placeholders['is_manual_order']['val'] = self::is_manual_order( $order ) ? 'بله' : 'خیر';
            $placeholders['receipt_url']['val']     = self::get_receipt_photo_url( $order );

            $placeholders['payment_method']['val']         = $order->get_payment_method();
            $placeholders['payment_method_title']['val']   = $order->get_payment_method_title();
            $placeholders['payment_transaction_id']['val'] = $order->get_transaction_id();

            foreach ( self::get_attribution_placeholder_defs() as $key => $data ) {
                $field = substr( $key, strlen( 'attribution_' ) );
                $placeholders[ $key ]['val'] = (string) $order->get_meta( '_wc_order_attribution_' . $field );
            }
        } else {
            foreach ( $placeholders as $k => $v ) {
                $placeholders[$k]['val'] = '(نمونه تستی)';
            }
        }

        return $placeholders;
    }

    /**
     * Definitions for WooCommerce Order Attribution placeholders.
     * Mirrors the field list tracked by WooCommerce core (meta prefix `_wc_order_attribution_`).
     */
    public static function get_attribution_placeholder_defs() {
        $fields = array(
            'source_type'          => 'نوع منبع (attribution)',
            'referrer'             => 'ارجاع‌دهنده (Referrer)',
            'utm_campaign'         => 'کمپین (utm_campaign)',
            'utm_source'           => 'منبع (utm_source)',
            'utm_medium'           => 'رسانه (utm_medium)',
            'utm_content'          => 'محتوا (utm_content)',
            'utm_id'               => 'شناسه UTM (utm_id)',
            'utm_term'             => 'عبارت جستجو (utm_term)',
            'utm_source_platform'  => 'پلتفرم منبع (utm_source_platform)',
            'utm_creative_format'  => 'فرمت تبلیغ (utm_creative_format)',
            'utm_marketing_tactic' => 'تاکتیک بازاریابی (utm_marketing_tactic)',
            'session_entry'        => 'صفحه ورود (session_entry)',
            'session_start_time'   => 'زمان شروع نشست',
            'session_pages'        => 'تعداد صفحات بازدید شده',
            'session_count'        => 'تعداد نشست‌ها',
            'user_agent'           => 'یوزر ایجنت مرورگر',
        );

        $placeholders = array();
        foreach ( $fields as $field => $label ) {
            $placeholders[ 'attribution_' . $field ] = array( 'label' => $label, 'val' => '' );
        }
        return $placeholders;
    }

    /**
     * Fetch order metadata key-value pairs.
     */
    public static function get_order_meta_variables( $order ) {
        $meta_vars = array();
        if ( ! $order ) {
            return $meta_vars;
        }
        
        foreach ( $order->get_meta_data() as $meta ) {
            $key = $meta->key;
            $val = $meta->value;
            
            if ( is_array( $val ) || is_object( $val ) ) {
                $val_str = wp_json_encode( $val );
            } else {
                $val_str = (string) $val;
            }
            
            if ( strlen( $val_str ) > 1000 ) {
                $val_str = substr( $val_str, 0, 1000 ) . '...';
            }
            
            $meta_vars[ $key ] = $val_str;
        }
        
        return $meta_vars;
    }

    /**
     * Get order conversion attribution source.
     */
    public static function get_order_source( WC_Order $order ) {
        return $order->get_meta( '_wc_order_attribution_source_type' )
            ?: $order->get_meta( '_order_source' )
            ?: $order->get_meta( '_utm_source' )
            ?: 'مستقیم';
    }

    /**
     * Resolve user-friendly shipping method names.
     */
    public static function get_shipping_method( WC_Order $order ) {
        $methods = $order->get_shipping_methods();
        if ( empty( $methods ) ) return 'نامشخص';

        $method  = reset( $methods );
        $rate_id = $method->get_meta( 'rate_id' ) ?: $method->get_method_id();

        $map = array(
            'post_shipping'          => 'ارسال پستی',
            'custom_choice_shipping' => 'تیپاکس (پس کرایه)',
            'local_pickup_shipping'  => 'تحویل حضوری',
            'taxi_khatti_shipping'   => 'تاکسی خطی (لنگرود - رشت)',
        );

        return $map[ $rate_id ] ?? ( $method->get_name() ?: $method->get_method_title() ?: 'نامشخص' );
    }

    /**
     * Store execution log inside database.
     */
    public static function log_entry( $order_id, $success, $error = '' ) {
        $log   = self::get_log();
        $log[] = array(
            'time'     => wp_date( 'Y-m-d H:i:s' ),
            'order_id' => $order_id,
            'success'  => $success,
            'error'    => $error,
        );
        if ( count( $log ) > 20 ) {
            $log = array_slice( $log, -20 );
        }
        update_option( 'bwn_log', $log, false );
    }

    /**
     * Get history log.
     */
    public static function get_log() {
        return get_option( 'bwn_log', array() );
    }
}
