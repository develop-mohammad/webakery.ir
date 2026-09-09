<?php
defined( 'ABSPATH' ) || exit;

/**
 * تبدیل نوشته/محصول به ایده و متن آمادهٔ کانال تلگرام.
 * بدون API خارجی — از خود محتوا استخراج می‌کند.
 */
class WBCN_Ideas {

	const MAX_LEN = 3900;

	/**
	 * @return array<string,array{label:string,hint:string,growth:string}>
	 */
	public static function formats() {
		return array(
			'tip'          => array(
				'label'  => 'نکته طلایی',
				'hint'   => 'یک برداشت سریع برای اسکرول‌کردن',
				'growth' => 'ذخیره و بازنشر بالا',
			),
			'checklist'    => array(
				'label'  => 'چک‌لیست کاربردی',
				'hint'   => 'کاربر ذخیره می‌کند و برمی‌گردد',
				'growth' => 'سیو و اشتراک در گروه‌ها',
			),
			'question'     => array(
				'label'  => 'سؤال تعاملی',
				'hint'   => 'نظر گرفتن یعنی دیده شدن در فید',
				'growth' => 'کامنت و بحث در کانال',
			),
			'mistake'      => array(
				'label'  => 'اشتباه رایج',
				'hint'   => 'کنجکاوی + حس «من هم این کار را می‌کردم»',
				'growth' => 'بازنشر در استوری/گروه',
			),
			'summary'      => array(
				'label'  => 'خلاصه ۶۰ ثانیه',
				'hint'   => 'ارزش کامل بدون خروج از تلگرام',
				'growth' => 'اعتماد و کلیک روی لینک',
			),
			'quote'        => array(
				'label'  => 'نقل‌قول',
				'hint'   => 'کوتاه، قابل اسکرین‌شات',
				'growth' => 'رشد ارگانیک با فوروارد',
			),
			'before_after' => array(
				'label'  => 'قبل و بعد',
				'hint'   => 'مشکل → راه‌حل از دل مطلب',
				'growth' => 'حس تحول؛ مناسب فروش',
			),
			'cta'          => array(
				'label'  => 'دعوت به مطالعه',
				'hint'   => 'قلاب + لینک سایت',
				'growth' => 'ترافیک به وردپرس',
			),
			'myth'         => array(
				'label'  => 'باور غلط',
				'hint'   => 'چالش ذهن مخاطب متخصص',
				'growth' => 'بحث تخصصی و اعتبار',
			),
			'thread'       => array(
				'label'  => 'رشته ۳ قسمتی',
				'hint'   => 'یک مطلب = سه پست پشت سر هم',
				'growth' => 'حضور بیشتر در فید امروز',
			),
		);
	}

	/**
	 * تقویم هفتگی رشد کانال از روی چند مطلب.
	 *
	 * @param array<int,array<string,string>> $items
	 * @return array<int,array<string,mixed>>
	 */
	public static function week_plan( array $items, $channel = '' ) {
		$days = array(
			array( 'day' => 'شنبه', 'format' => 'tip', 'why' => 'شروع هفته با ارزش سریع' ),
			array( 'day' => 'یکشنبه', 'format' => 'question', 'why' => 'تعامل؛ الگوریتم تلگرام پست پرحرف را نشان می‌دهد' ),
			array( 'day' => 'دوشنبه', 'format' => 'checklist', 'why' => 'محتوای ذخیره‌شدنی' ),
			array( 'day' => 'سه‌شنبه', 'format' => 'mistake', 'why' => 'قلاب احساسی بدون فروش مستقیم' ),
			array( 'day' => 'چهارشنبه', 'format' => 'summary', 'why' => 'هدایت به مطلب کامل سایت' ),
			array( 'day' => 'پنجشنبه', 'format' => 'quote', 'why' => 'پست سبک قبل از تعطیلی' ),
			array( 'day' => 'جمعه', 'format' => 'cta', 'why' => 'جمع‌بندی هفته + دعوت عضو جدید' ),
		);

		$plan  = array();
		$count = count( $items );
		foreach ( $days as $i => $row ) {
			$item = $count ? $items[ $i % $count ] : self::sample_item();
			$built = self::build( $item, $row['format'], $channel );
			$plan[] = array(
				'day'     => $row['day'],
				'format'  => $row['format'],
				'label'   => self::formats()[ $row['format'] ]['label'],
				'why'     => $row['why'],
				'title'   => $item['title'] ?? '',
				'url'     => $item['url'] ?? '',
				'text'    => $built['text'],
				'ok'      => ! empty( $built['ok'] ),
			);
		}
		return $plan;
	}

	/**
	 * همهٔ قالب‌ها برای یک مطلب.
	 *
	 * @param array<string,string> $item
	 * @return array<string,array<string,mixed>>
	 */
	public static function all_for( array $item, $channel = '' ) {
		$out = array();
		foreach ( array_keys( self::formats() ) as $key ) {
			$out[ $key ] = self::build( $item, $key, $channel );
		}
		return $out;
	}

	/**
	 * @param array<string,string> $item title, excerpt, content, url, type, site
	 * @return array{ok:bool,format:string,label:string,text:string,parts?:array}
	 */
	public static function build( array $item, $format, $channel = '' ) {
		$item   = self::normalize_item( $item );
		$plain  = self::plain( $item['content'] !== '' ? $item['content'] : $item['excerpt'] );
		$sents  = self::sentences( $plain );
		$points = self::points( $plain, $sents, $item['title'] );
		$quote  = self::best_quote( $sents, $item['title'] );
		$link   = self::with_utm( $item['url'] );
		$sig    = self::signature( $channel );

		$builder = array(
			'tip'          => 'fmt_tip',
			'checklist'    => 'fmt_checklist',
			'question'     => 'fmt_question',
			'mistake'      => 'fmt_mistake',
			'summary'      => 'fmt_summary',
			'quote'        => 'fmt_quote',
			'before_after' => 'fmt_before_after',
			'cta'          => 'fmt_cta',
			'myth'         => 'fmt_myth',
			'thread'       => 'fmt_thread',
		);

		$method = $builder[ $format ] ?? 'fmt_summary';
		$result = self::$method( $item, $sents, $points, $quote, $link, $sig );

		if ( isset( $result['parts'] ) && is_array( $result['parts'] ) ) {
			foreach ( $result['parts'] as $i => $part ) {
				$result['parts'][ $i ] = self::clip( $part );
			}
			$result['text'] = implode( "\n\n————————\n\n", $result['parts'] );
		} else {
			$result['text'] = self::clip( (string) ( $result['text'] ?? '' ) );
		}

		$meta = self::formats()[ $format ] ?? array( 'label' => $format );
		return array(
			'ok'     => $result['text'] !== '',
			'format' => $format,
			'label'  => $meta['label'],
			'hint'   => $meta['hint'] ?? '',
			'text'   => $result['text'],
			'parts'  => $result['parts'] ?? array( $result['text'] ),
		);
	}

	/** @param array<string,string> $item */
	public static function normalize_item( array $item ) {
		return array(
			'title'   => self::clean_line( (string) ( $item['title'] ?? '' ) ),
			'excerpt' => self::plain( (string) ( $item['excerpt'] ?? '' ) ),
			'content' => (string) ( $item['content'] ?? '' ),
			'url'     => trim( (string) ( $item['url'] ?? '' ) ),
			'type'    => sanitize_key_fallback( (string) ( $item['type'] ?? 'post' ) ),
			'site'    => trim( (string) ( $item['site'] ?? '' ) ),
		);
	}

	public static function sample_item() {
		return array(
			'title'   => 'ورود آسان با پیامک؛ بدون رمز فراموش‌شده',
			'excerpt' => 'مشتری با شماره موبایل وارد می‌شود و دیگر رمز سایت را گم نمی‌کند.',
			'content' => 'خیلی از فروشگاه‌ها هنوز با فرم ورود پیش‌فرض وردپرس کار می‌کنند. مشتری رمز را فراموش می‌کند و سبد را رها می‌کند. ورود با پیامک یعنی کمتر اصطکاک، ثبت‌نام سریع‌تر و شماره موبایل واقعی. پنل‌های ایرانی مثل ملی‌پیامک و کاوه‌نگار را وصل کنید. کد یک‌بارمصرف را هش کنید و محدودیت ارسال بگذارید. رنگ و فونت را از قالب به ارث ببرید تا با المنتور هماهنگ باشد.',
			'url'     => 'https://webakery.ir/',
			'type'    => 'post',
			'site'    => 'webakery.ir',
		);
	}

	public static function plain( $html ) {
		$html = (string) $html;
		$html = preg_replace( '/<(script|style)[^>]*>.*?<\/\1>/is', ' ', $html );
		$html = preg_replace( '/<li[^>]*>/i', "\n• ", $html );
		$html = preg_replace( '/<br\s*\/?>/i', "\n", $html );
		$html = preg_replace( '/<\/(p|h[1-6]|div|tr)>/i', "\n", $html );
		$text = html_entity_decode( strip_tags( (string) $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = preg_replace( '/[ \t]+/u', ' ', $text );
		$text = preg_replace( "/\n{3,}/u", "\n\n", (string) $text );
		return trim( (string) $text );
	}

	/**
	 * @return string[]
	 */
	public static function sentences( $text ) {
		$text = trim( (string) $text );
		if ( $text === '' ) {
			return array();
		}
		$parts = preg_split( '/(?<=[.!?؟۔])\s+/u', $text ) ?: array();
		$out   = array();
		foreach ( $parts as $p ) {
			$p = trim( $p, " \t\n\r\0\x0B«»\"'،,;؛" );
			if ( self::len( $p ) < 12 ) {
				continue;
			}
			$out[] = $p;
		}
		return array_slice( $out, 0, 24 );
	}

	/**
	 * @param string[] $sents
	 * @return string[]
	 */
	public static function points( $plain, array $sents, $title ) {
		$points = array();
		if ( preg_match_all( '/(?:^|\n)\s*(?:[•\-\*✅]|\d+[\.\)])\s+(.+)/u', $plain, $m ) ) {
			foreach ( $m[1] as $line ) {
				$line = trim( $line );
				if ( self::len( $line ) >= 8 && self::len( $line ) <= 140 ) {
					$points[] = $line;
				}
			}
		}
		if ( count( $points ) < 3 ) {
			foreach ( $sents as $s ) {
				if ( self::len( $s ) <= 160 ) {
					$points[] = rtrim( $s, '.۔' );
				}
				if ( count( $points ) >= 6 ) {
					break;
				}
			}
		}
		if ( ! $points && $title ) {
			$points[] = $title;
		}
		$uniq = array();
		foreach ( $points as $p ) {
			$k = self::clip( $p, 80 );
			$uniq[ $k ] = $p;
		}
		return array_values( array_slice( $uniq, 0, 6 ) );
	}

	/**
	 * @param string[] $sents
	 */
	public static function best_quote( array $sents, $title ) {
		$best = $title;
		$score = 0;
		foreach ( $sents as $s ) {
			$n = self::len( $s );
			if ( $n < 28 || $n > 180 ) {
				continue;
			}
			$sc = $n;
			if ( preg_match( '/(نباید|باید|مهم|اشتباه|راز|نکته|هرگز|همیشه)/u', $s ) ) {
				$sc += 40;
			}
			if ( $sc > $score ) {
				$score = $sc;
				$best  = $s;
			}
		}
		return rtrim( (string) $best, '.۔' );
	}

	public static function clip( $text, $max = null ) {
		$max  = $max ? (int) $max : self::MAX_LEN;
		$text = trim( (string) $text );
		if ( self::len( $text ) <= $max ) {
			return $text;
		}
		if ( function_exists( 'mb_substr' ) ) {
			return rtrim( mb_substr( $text, 0, $max - 1, 'UTF-8' ) ) . '…';
		}
		return rtrim( substr( $text, 0, $max - 1 ) ) . '…';
	}

	public static function len( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text, 'UTF-8' ) : strlen( (string) $text );
	}

	public static function escape( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	public static function with_utm( $url ) {
		$url = trim( (string) $url );
		if ( $url === '' || ! preg_match( '#^https?://#i', $url ) ) {
			return $url;
		}
		$utm = 'utm_source=telegram&utm_medium=channel&utm_campaign=webakery-channel';
		return strpos( $url, '?' ) === false ? $url . '?' . $utm : $url . '&' . $utm;
	}

	public static function signature( $channel ) {
		$channel = ltrim( trim( (string) $channel ), '@' );
		if ( $channel === '' ) {
			return '';
		}
		if ( preg_match( '/^-?\d+$/', $channel ) ) {
			return '';
		}
		return "\n\n" . self::escape( '@' . $channel );
	}

	public static function link_line( $url ) {
		if ( $url === '' ) {
			return '';
		}
		$safe = self::escape( $url );
		return "\n\n🔗 <a href=\"{$safe}\">ادامه مطلب</a>";
	}

	private static function fmt_tip( array $item, array $sents, array $points, $quote, $link, $sig ) {
		$tip = $points[0] ?? $quote;
		$text = "💡 <b>نکته طلایی</b>\n\n"
			. '<b>' . self::escape( $item['title'] ) . "</b>\n\n"
			. self::escape( $tip ) . '.'
			. self::link_line( $link )
			. $sig;
		return array( 'text' => $text );
	}

	private static function fmt_checklist( array $item, array $sents, array $points, $quote, $link, $sig ) {
		$lines = array();
		foreach ( array_slice( $points, 0, 5 ) as $i => $p ) {
			$lines[] = ( $i + 1 ) . '. ' . self::escape( rtrim( $p, '.۔' ) );
		}
		if ( count( $lines ) < 3 ) {
			$lines[] = '۳. یک کار کوچک را همین امروز اجرا کنید';
		}
		$text = "✅ <b>چک‌لیست</b>\n"
			. '<b>' . self::escape( $item['title'] ) . "</b>\n\n"
			. implode( "\n", $lines )
			. "\n\nذخیره‌اش کنید تا بعداً مرور کنید."
			. self::link_line( $link )
			. $sig;
		return array( 'text' => $text );
	}

	private static function fmt_question( array $item, array $sents, array $points, $quote, $link, $sig ) {
		$q = self::as_question( $item['title'] );
		$text = "❓ <b>سؤال برای شما</b>\n\n"
			. self::escape( $q ) . "\n\n"
			. 'یک خط در نظرات بنویسید — تجربه‌تان برای بقیه هم مفید است.'
			. ( $points ? "\n\nشروع بحث: " . self::escape( rtrim( $points[0], '.۔' ) ) . '.' : '' )
			. self::link_line( $link )
			. $sig;
		return array( 'text' => $text );
	}

	private static function fmt_mistake( array $item, array $sents, array $points, $quote, $link, $sig ) {
		$bad = $points[0] ?? $item['title'];
		$text = "⚠️ <b>اشتباه رایجی که گران تمام می‌شود</b>\n\n"
			. 'خیلی‌ها در موضوع «' . self::escape( $item['title'] ) . "» همین کار را می‌کنند:\n\n"
			. '❌ ' . self::escape( rtrim( $bad, '.۔' ) ) . "\n\n"
			. '✅ به‌جایش: ' . self::escape( rtrim( $points[1] ?? $quote, '.۔' ) ) . '.'
			. self::link_line( $link )
			. $sig;
		return array( 'text' => $text );
	}

	private static function fmt_summary( array $item, array $sents, array $points, $quote, $link, $sig ) {
		$take = array_slice( $sents ? $sents : $points, 0, 3 );
		$body = array();
		foreach ( $take as $s ) {
			$body[] = '• ' . self::escape( rtrim( $s, '.۔' ) );
		}
		$text = "⏱ <b>در ۶۰ ثانیه</b>\n"
			. '<b>' . self::escape( $item['title'] ) . "</b>\n\n"
			. implode( "\n", $body )
			. self::link_line( $link )
			. $sig;
		return array( 'text' => $text );
	}

	private static function fmt_quote( array $item, array $sents, array $points, $quote, $link, $sig ) {
		$text = "📌 <b>یک جمله از مطلب</b>\n\n"
			. '«' . self::escape( $quote ) . "»\n\n"
			. '<i>' . self::escape( $item['title'] ) . '</i>'
			. self::link_line( $link )
			. $sig;
		return array( 'text' => $text );
	}

	private static function fmt_before_after( array $item, array $sents, array $points, $quote, $link, $sig ) {
		$text = "🔄 <b>قبل و بعد</b>\n\n"
			. '<b>' . self::escape( $item['title'] ) . "</b>\n\n"
			. 'قبل: سردرگمی، کار دستی، نتیجه نامشخص.' . "\n"
			. 'بعد: ' . self::escape( rtrim( $points[0] ?? $quote, '.۔' ) ) . ".\n\n"
			. 'اگر در مرحلهٔ «قبل» هستید، مطلب کامل مسیر را نشان می‌دهد.'
			. self::link_line( $link )
			. $sig;
		return array( 'text' => $text );
	}

	private static function fmt_cta( array $item, array $sents, array $points, $quote, $link, $sig ) {
		$hook = $item['excerpt'] !== '' ? $item['excerpt'] : ( $sents[0] ?? $item['title'] );
		$text = "🎯 <b>اگر فقط یک مطلب بخوانید</b>\n\n"
			. '<b>' . self::escape( $item['title'] ) . "</b>\n\n"
			. self::escape( self::clip( $hook, 280 ) )
			. self::link_line( $link )
			. $sig;
		return array( 'text' => $text );
	}

	private static function fmt_myth( array $item, array $sents, array $points, $quote, $link, $sig ) {
		$text = "🧠 <b>باور غلط</b>\n\n"
			. '«برای «' . self::escape( $item['title'] ) . "» یک راه‌حل جادویی کافی است.»\n\n"
			. 'واقعیت: ' . self::escape( rtrim( $quote, '.۔' ) ) . ".\n\n"
			. 'جزئیات را در مطلب بخوانید؛ خلاصه‌سازی بیش از حد معمولاً همان‌جایی است که کار خراب می‌شود.'
			. self::link_line( $link )
			. $sig;
		return array( 'text' => $text );
	}

	private static function fmt_thread( array $item, array $sents, array $points, $quote, $link, $sig ) {
		$p1 = "🧵 <b>۱/۳</b> " . self::escape( $item['title'] ) . "\n\n"
			. self::escape( rtrim( $sents[0] ?? $points[0] ?? $item['title'], '.۔' ) ) . '.';
		$p2 = "🧵 <b>۲/۳</b>\n\n"
			. self::escape( rtrim( $sents[1] ?? $points[1] ?? $quote, '.۔' ) ) . '.';
		$p3 = "🧵 <b>۳/۳</b>\n\n"
			. self::escape( rtrim( $sents[2] ?? $points[0] ?? $quote, '.۔' ) ) . '.'
			. self::link_line( $link )
			. $sig;
		return array(
			'text'  => $p1 . "\n\n————————\n\n" . $p2 . "\n\n————————\n\n" . $p3,
			'parts' => array( $p1, $p2, $p3 ),
		);
	}

	public static function as_question( $title ) {
		$title = trim( (string) $title );
		if ( $title === '' ) {
			return 'شما در این موضوع چه تجربه‌ای دارید؟';
		}
		if ( preg_match( '/[؟?]$/u', $title ) ) {
			return $title;
		}
		return 'شما دربارهٔ «' . $title . '» چه تجربه‌ای دارید؟ کدام بخش برای‌تان سخت‌تر بوده؟';
	}

	public static function clean_line( $text ) {
		$text = self::plain( $text );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return trim( (string) $text );
	}

	/**
	 * پست آمادهٔ انتشار مطلب (نه ایدهٔ بازنویسی‌شده).
	 *
	 * @param array<string,string> $item
	 */
	public static function publish_post( array $item, $channel = '', $include_excerpt = true ) {
		$item = self::normalize_item( $item );
		$ex   = $item['excerpt'] !== '' ? $item['excerpt'] : self::clip( self::plain( $item['content'] ), 280 );
		$text = '🆕 <b>' . self::escape( $item['title'] ) . '</b>';
		if ( $include_excerpt && $ex !== '' ) {
			$text .= "\n\n" . self::escape( $ex );
		}
		$text .= self::link_line( self::with_utm( $item['url'] ) );
		$text .= self::signature( $channel );
		return self::clip( $text );
	}
}

if ( ! function_exists( 'sanitize_key_fallback' ) ) {
	/**
	 * @param string $key
	 */
	function sanitize_key_fallback( $key ) {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $key );
		}
		$key = strtolower( (string) $key );
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}
