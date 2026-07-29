<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWN_Jalali {
    /**
     * Get the Jalali date string for a WooCommerce order.
     */
    public static function order_date( WC_Order $order ) {
        $dt = $order->get_date_created();
        if ( ! $dt ) return '';

        $ts = $dt->getTimestamp();
        return self::timestamp_to_jalali( $ts );
    }

    /**
     * Convert timestamp to Jalali string.
     */
    public static function timestamp_to_jalali( $timestamp ) {
        // Not wp_date() — its output is filterable, and the theme's own
        // admin-side Jalali display (Formol1\Core\JalaliAdmin) hooks the
        // `wp_date` filter whenever is_admin() is true. An order update is
        // itself an admin-context request, so calling wp_date('Y'/'n'/'j',
        // ...) here would come back already Jalali-converted, and
        // from_gregorian() below would convert those digits a *second*
        // time — corrupting the result (this is what produced the wrong
        // {order_date} in notification messages). A plain DateTime in the
        // site's real timezone gives the true Gregorian Y/M/D
        // unconditionally, bypassing every filter — for these three purely
        // numeric format characters that's exactly what wp_date() itself
        // computes internally before any filter runs.
        $dt = new DateTime( '@' . $timestamp );
        $dt->setTimezone( wp_timezone() );

        $gy = (int) $dt->format( 'Y' );
        $gm = (int) $dt->format( 'n' );
        $gd = (int) $dt->format( 'j' );

        return self::from_gregorian( $gy, $gm, $gd );
    }

    /**
     * Get today's Jalali date string.
     */
    public static function today() {
        return self::timestamp_to_jalali( current_time( 'timestamp' ) );
    }

    /**
     * Gregorian → Jalali (jdf standard algorithm).
     */
    public static function from_gregorian( $gy, $gm, $gd ) {
        $g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );

        if ( $gy > 1600 ) {
            $jy = 979;
            $gy -= 1600;
        } else {
            $jy = 0;
            $gy -= 621;
        }

        $gy2 = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;

        $days = ( 365 * $gy )
              + intval( ( $gy2 + 3 ) / 4 )
              - intval( ( $gy2 + 99 ) / 100 )
              + intval( ( $gy2 + 399 ) / 400 )
              - 80
              + $gd
              + $g_d_m[ $gm - 1 ];

        $jy += 33 * intval( $days / 12053 );
        $days %= 12053;

        $jy += 4 * intval( $days / 1461 );
        $days %= 1461;

        if ( $days > 365 ) {
            $jy += intval( ( $days - 1 ) / 365 );
            $days = ( $days - 1 ) % 365;
        }

        if ( $days < 186 ) {
            $jm = 1 + intval( $days / 31 );
            $jd = 1 + ( $days % 31 );
        } else {
            $jm = 7 + intval( ( $days - 186 ) / 30 );
            $jd = 1 + ( ( $days - 186 ) % 30 );
        }

        return self::format( $jy, $jm, $jd );
    }

    /**
     * Format Jalali date into standard readable string.
     */
    private static function format( $jy, $jm, $jd ) {
        $months = array(
            'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
            'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
        );
        return "{$jd} {$months[ $jm - 1 ]} {$jy}";
    }
}
