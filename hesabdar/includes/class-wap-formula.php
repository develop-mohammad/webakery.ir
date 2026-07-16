<?php
defined( 'ABSPATH' ) || exit;

/**
 * ارزیاب امن فرمول‌های ساده شبیه اکسل — بدون eval().
 * پشتیبانی از: + - * / ( )، اعداد اعشاری، متغیرهای نام‌دار (GROSS، NET، COUNT، ...)
 * و چند تابع پرکاربرد: ROUND, ABS, MIN, MAX, SUM, IF.
 */
class WAP_Formula {

    /**
     * @return array{ok:bool, value:?float, error:?string}
     */
    public static function evaluate( string $expr, array $vars ): array {
        try {
            $tokens = self::tokenize( $expr );
            $pos    = 0;
            $value  = self::parse_expr( $tokens, $pos, $vars );
            if ( $pos < count( $tokens ) ) {
                throw new Exception( 'نویسه غیرمنتظره: ' . $tokens[ $pos ]['value'] );
            }
            return array( 'ok' => true, 'value' => $value, 'error' => null );
        } catch ( Throwable $e ) {
            return array( 'ok' => false, 'value' => null, 'error' => $e->getMessage() );
        }
    }

    private static function tokenize( string $expr ): array {
        $tokens = array();
        $i      = 0;
        $len    = strlen( $expr );
        while ( $i < $len ) {
            $ch = $expr[ $i ];
            if ( ctype_space( $ch ) ) { $i++; continue; }
            if ( ctype_digit( $ch ) || $ch === '.' ) {
                $start = $i;
                while ( $i < $len && ( ctype_digit( $expr[ $i ] ) || $expr[ $i ] === '.' ) ) $i++;
                $tokens[] = array( 'type' => 'num', 'value' => substr( $expr, $start, $i - $start ) );
                continue;
            }
            if ( ctype_alpha( $ch ) || $ch === '_' ) {
                $start = $i;
                while ( $i < $len && ( ctype_alnum( $expr[ $i ] ) || $expr[ $i ] === '_' ) ) $i++;
                $tokens[] = array( 'type' => 'ident', 'value' => substr( $expr, $start, $i - $start ) );
                continue;
            }
            if ( strpos( '+-*/(),', $ch ) !== false ) {
                $tokens[] = array( 'type' => 'op', 'value' => $ch );
                $i++;
                continue;
            }
            throw new Exception( 'نویسه غیرمجاز: «' . $ch . '»' );
        }
        return $tokens;
    }

    private static function peek( array $tokens, int $pos ) {
        return $tokens[ $pos ] ?? null;
    }

    private static function parse_expr( array $tokens, int &$pos, array $vars ): float {
        $value = self::parse_term( $tokens, $pos, $vars );
        while ( ( $t = self::peek( $tokens, $pos ) ) && $t['type'] === 'op' && in_array( $t['value'], array( '+', '-' ), true ) ) {
            $pos++;
            $rhs = self::parse_term( $tokens, $pos, $vars );
            $value = $t['value'] === '+' ? $value + $rhs : $value - $rhs;
        }
        return $value;
    }

    private static function parse_term( array $tokens, int &$pos, array $vars ): float {
        $value = self::parse_factor( $tokens, $pos, $vars );
        while ( ( $t = self::peek( $tokens, $pos ) ) && $t['type'] === 'op' && in_array( $t['value'], array( '*', '/' ), true ) ) {
            $pos++;
            $rhs = self::parse_factor( $tokens, $pos, $vars );
            if ( $t['value'] === '/' ) {
                if ( (float) $rhs === 0.0 ) throw new Exception( 'تقسیم بر صفر' );
                $value = $value / $rhs;
            } else {
                $value = $value * $rhs;
            }
        }
        return $value;
    }

    private static function parse_factor( array $tokens, int &$pos, array $vars ): float {
        $t = self::peek( $tokens, $pos );
        if ( $t && $t['type'] === 'op' && ( $t['value'] === '+' || $t['value'] === '-' ) ) {
            $pos++;
            $val = self::parse_factor( $tokens, $pos, $vars );
            return $t['value'] === '-' ? -$val : $val;
        }
        return self::parse_primary( $tokens, $pos, $vars );
    }

    private static function parse_primary( array $tokens, int &$pos, array $vars ): float {
        $t = self::peek( $tokens, $pos );
        if ( ! $t ) throw new Exception( 'فرمول ناقص است' );

        if ( $t['type'] === 'num' ) {
            $pos++;
            return (float) $t['value'];
        }

        if ( $t['type'] === 'op' && $t['value'] === '(' ) {
            $pos++;
            $val = self::parse_expr( $tokens, $pos, $vars );
            $close = self::peek( $tokens, $pos );
            if ( ! $close || $close['value'] !== ')' ) throw new Exception( 'پرانتز بسته نشده' );
            $pos++;
            return $val;
        }

        if ( $t['type'] === 'ident' ) {
            $name = strtoupper( $t['value'] );
            $pos++;
            $next = self::peek( $tokens, $pos );
            if ( $next && $next['type'] === 'op' && $next['value'] === '(' ) {
                // فراخوانی تابع
                $pos++;
                $args = array();
                $close = self::peek( $tokens, $pos );
                if ( ! $close || $close['value'] !== ')' ) {
                    $args[] = self::parse_expr( $tokens, $pos, $vars );
                    while ( ( $c = self::peek( $tokens, $pos ) ) && $c['value'] === ',' ) {
                        $pos++;
                        $args[] = self::parse_expr( $tokens, $pos, $vars );
                    }
                }
                $close = self::peek( $tokens, $pos );
                if ( ! $close || $close['value'] !== ')' ) throw new Exception( 'پرانتز تابع بسته نشده' );
                $pos++;
                return self::call_function( $name, $args );
            }
            if ( ! array_key_exists( $name, $vars ) ) {
                throw new Exception( 'متغیر ناشناخته: ' . $name );
            }
            return (float) $vars[ $name ];
        }

        throw new Exception( 'عبارت نامعتبر' );
    }

    private static function call_function( string $name, array $args ): float {
        switch ( $name ) {
            case 'ROUND':
                return round( $args[0] ?? 0, (int) ( $args[1] ?? 0 ) );
            case 'ABS':
                return abs( $args[0] ?? 0 );
            case 'MIN':
                if ( empty( $args ) ) throw new Exception( 'MIN به حداقل یک آرگومان نیاز دارد' );
                return min( $args );
            case 'MAX':
                if ( empty( $args ) ) throw new Exception( 'MAX به حداقل یک آرگومان نیاز دارد' );
                return max( $args );
            case 'SUM':
                return array_sum( $args );
            case 'IF':
                if ( count( $args ) < 2 ) throw new Exception( 'IF به حداقل ۲ آرگومان نیاز دارد (شرط، مقدار)' );
                return ( $args[0] != 0 ) ? $args[1] : ( $args[2] ?? 0 );
            default:
                throw new Exception( 'تابع ناشناخته: ' . $name );
        }
    }
}
