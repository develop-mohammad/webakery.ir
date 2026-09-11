<?php
defined( 'ABSPATH' ) || exit;

/**
 * متن قرارداد نهال و جایگزینی فیلدها.
 */
class NCK_Contract {

	public static function default_intro() {
		return 'خوشحالیم که نهال را برای کار، تمرکز و رشد خود انتخاب کرده‌اید.';
	}

	/**
	 * جمله‌های قدیمی مقدمه که باید با متن فعلی جایگزین شوند.
	 *
	 * @return array<int, string>
	 */
	public static function legacy_intros() {
		return array(
			'خواستیم که «نهال» را برای کار، تمرکز و رشد خود انتخاب کرده‌اید.',
			'خوشحالیم که «نهال» را برای کار، تمرکز و رشد خود انتخاب کرده‌اید.',
		);
	}

	public static function legacy_intro() {
		$all = self::legacy_intros();
		return $all[0];
	}

	public static function default_preamble() {
		return 'این قرارداد بین «{{org}}» و «{{title}} {{name}}» با شماره تماس {{phone}} منعقد می‌گردد.';
	}

	public static function default_sections() {
		return array(
			array(
				'title' => 'موضوع قرارداد',
				'body'  => 'ارائه خدمات فضای کار اشتراکی در محیط مشخص و از پیش تعیین‌شده در مجموعه نهال، جهت انجام فعالیت‌های کاری، مطالعاتی و فردی.',
			),
			array(
				'title' => 'محدوده استفاده از فضا',
				'body'  => 'کاربر متعهد می‌گردد از فضای تعیین‌شده برای کار اشتراکی استفاده نماید و امکان استقرار در سایر فضاهای مجموعه وجود ندارد. استفاده از دیگر فضاهای مجموعه صرفاً در حد استراحت کوتاه، هاوری و تعامل کوتاه (گپ و گفت) مجاز است.',
			),
			array(
				'title' => 'برنامه‌های فرهنگی و ویژه',
				'body'  => "نهال یک مجموعه فرهنگی فعال است و گاهی میزبان برنامه‌ها و رویدادهای ویژه می‌باشد. در چنین مواقعی ممکن است فضای کار اشتراکی به‌صورت موقت تغییر کند و فضای جایگزین دیگری در اختیار شما قرار گیرد.\nاطلاع‌رسانی این موارد از قبل انجام می‌شود و تلاش ما همیشه حفظ کیفیت تجربه شماست.",
			),
			array(
				'title' => 'انتخاب شیفت استفاده',
				'body'  => "هر اشتراک شامل {{shifts}} شیفت استفاده در ماه می‌باشد.\nکاربران می‌توانند متناسب با برنامه کاری خود از این شیفت‌ها استفاده کنند. برای مثال: استفاده روزانه از یک شیفت یا حضور در شیفت صبح یک روز و در نتیجه تعداد روزهای کمتر.\nهر حضور در شیفت صبح یا عصر، به عنوان یک شیفت استفاده محاسبه می‌گردد.\nدر صورت تمایل به استفاده از هر دو شیفت، امکان تهیه اشتراک جداگانه برای هر کدام وجود دارد.",
			),
			array(
				'title' => 'ساعات کاری مجموعه',
				'body'  => "شیفت صبح: {{morning}}\nشیفت عصر: {{evening}}\n\nمجموعه در تعطیلات رسمی در شیفت صبح تعطیل می‌باشد.\nهمچنین در برخی تعطیلات خاص، مجموعه به‌طور کامل تعطیل خواهد بود که اطلاع‌رسانی آن از قبل انجام می‌شود.",
			),
			array(
				'title' => 'فرهنگ استفاده از فضا',
				'body'  => "این فضا بر پایه احترام، تمرکز و آرامش جمعی شکل گرفته است.\nاز همراهی شما در رعایت سکوت نسبی، احترام به دیگران و حفظ این فضای ارزشمند صمیمانه سپاسگزاریم.",
			),
		);
	}

	public static function title_label( $title ) {
		if ( 'ms' === $title ) {
			return 'خانم';
		}
		if ( 'mr' === $title ) {
			return 'آقای';
		}
		return 'آقای/خانم';
	}

	public static function blank( $width = 18 ) {
		return str_repeat( '…', max( 6, (int) $width ) );
	}

	public static function fill_template( $text, array $vars ) {
		$search  = array();
		$replace = array();
		foreach ( $vars as $key => $value ) {
			$search[]  = '{{' . $key . '}}';
			$replace[] = (string) $value;
		}
		return str_replace( $search, $replace, (string) $text );
	}

	public static function vars_from( array $input ) {
		$name  = trim( isset( $input['name'] ) ? (string) $input['name'] : '' );
		$phone = trim( isset( $input['phone'] ) ? (string) $input['phone'] : '' );
		$title = isset( $input['title'] ) ? (string) $input['title'] : '';
		$plan  = isset( $input['plan'] ) ? (string) $input['plan'] : '';
		$plans = NCK_Shifts::plan_labels();

		return array(
			'org'     => isset( $input['org'] ) ? (string) $input['org'] : 'مجموعه فرهنگی نهال',
			'title'   => self::title_label( $title ),
			'name'    => $name !== '' ? $name : self::blank( 16 ),
			'phone'   => $phone !== '' ? NCK_Jalali::fa_digits( $phone ) : self::blank( 12 ),
			'date'    => isset( $input['date'] ) ? (string) $input['date'] : '',
			'shifts'  => isset( $input['shifts'] ) ? NCK_Jalali::fa_digits( $input['shifts'] ) : NCK_Jalali::fa_digits( 26 ),
			'morning' => isset( $input['morning'] ) ? (string) $input['morning'] : '۸:۰۰ الی ۱۳:۰۰',
			'evening' => isset( $input['evening'] ) ? (string) $input['evening'] : '۱۶:۳۰ الی ۲۲:۰۰',
			'plan'    => isset( $plans[ $plan ] ) ? $plans[ $plan ] : '—',
		);
	}

	public static function hours_label( $start, $end ) {
		$a = NCK_Jalali::fa_digits( (string) $start );
		$b = NCK_Jalali::fa_digits( (string) $end );
		return $a . ' الی ' . $b;
	}

	/**
	 * @return array{ok:bool,message:string}
	 */
	public static function validate_signature( $data_url ) {
		$data = (string) $data_url;
		if ( strlen( $data ) < 80 || strlen( $data ) > 500000 ) {
			return array( 'ok' => false, 'message' => 'امضا کامل نیست. لطفاً داخل کادر امضا کنید.' );
		}
		if ( ! preg_match( '#^data:image/(png|jpeg);base64,#', $data ) ) {
			return array( 'ok' => false, 'message' => 'قالب امضا نامعتبر است.' );
		}
		$comma = strpos( $data, ',' );
		$raw   = base64_decode( substr( $data, $comma + 1 ), true );
		if ( ! $raw || strlen( $raw ) < 40 ) {
			return array( 'ok' => false, 'message' => 'امضا ذخیره نشد. دوباره امضا کنید.' );
		}
		$info = @getimagesizefromstring( $raw ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $info ) || empty( $info[0] ) || empty( $info[1] ) ) {
			return array( 'ok' => false, 'message' => 'تصویر امضا خوانده نشد.' );
		}
		if ( (int) $info[0] < 80 || (int) $info[1] < 40 ) {
			return array( 'ok' => false, 'message' => 'کادر امضا خیلی کوچک است.' );
		}
		return array( 'ok' => true, 'message' => '' );
	}

	public static function allowed_html() {
		return array(
			'p'      => array(),
			'br'     => array(),
			'strong' => array(),
			'em'     => array(),
			'span'   => array( 'class' => true ),
			'h2'     => array(),
			'h3'     => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
		);
	}
}
