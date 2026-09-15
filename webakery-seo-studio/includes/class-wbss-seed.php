<?php
defined( 'ABSPATH' ) || exit;

/**
 * دادهٔ نمونه برای اولین اجرا — داشبورد خالی نباشد.
 */
class WBSS_Seed {

	public static function maybe_seed() {
		if ( get_option( 'wbss_seeded' ) ) {
			return;
		}
		if ( WBSS_DB::projects() ) {
			update_option( 'wbss_seeded', 1, false );
			return;
		}
		self::run();
	}

	public static function run() {
		$pid = WBSS_DB::save_project(
			array(
				'name'   => 'وب‌آکری',
				'domain' => 'https://webakery.ir',
				'notes'  => 'پروژه نمونهٔ سئو استودیو — می‌توانید پاک کنید یا پروژه خودتان را بسازید.',
			)
		);
		if ( is_wp_error( $pid ) || ! $pid ) {
			return 0;
		}

		$today = strtotime( current_time( 'Y-m-d' ) );
		$g     = static function ( $days_ago ) use ( $today ) {
			return gmdate( 'Y-m-d', $today - ( $days_ago * DAY_IN_SECONDS ) );
		};

		$keywords = array(
			array( 'افزونه وردپرس', 'commercial', 2400, 48, 'https://webakery.ir', array( 18, 16, 14, 11, 9, 8, 6 ) ),
			array( 'افزونه نوبت‌دهی', 'transactional', 880, 36, 'https://webakery.ir/nobat-man', array( 28, 22, 19, 15, 12, 9, 7 ) ),
			array( 'حسابداری ووکامرس', 'commercial', 720, 41, 'https://webakery.ir/hesabdar', array( 34, 29, 24, 21, 18, 14, 11 ) ),
			array( 'چت آنلاین سایت', 'transactional', 1300, 44, 'https://webakery.ir/chat', array( 12, 11, 9, 8, 7, 6, 4 ) ),
			array( 'لایسنس افزونه وردپرس', 'informational', 390, 29, 'https://webakery.ir/license', array( 41, 35, 30, 26, 22, 19, 15 ) ),
			array( 'سئو سایت وردپرسی', 'informational', 1900, 55, 'https://webakery.ir/blog/seo', array( 52, 44, 38, 31, 27, 23, 19 ) ),
			array( 'فرم پرداخت ووکامرس', 'transactional', 540, 33, 'https://webakery.ir/baget', array( 16, 15, 13, 12, 10, 9, 8 ) ),
			array( 'رزرو نوبت آنلاین', 'transactional', 2100, 50, 'https://webakery.ir/nobat-man', array( 25, 21, 18, 16, 14, 12, 10 ) ),
		);

		$offsets = array( 42, 35, 28, 21, 14, 7, 0 );
		$kw_ids  = array();

		foreach ( $keywords as $i => $k ) {
			$id = WBSS_DB::save_keyword(
				array(
					'project_id' => $pid,
					'keyword'    => $k[0],
					'intent'     => $k[1],
					'volume'     => $k[2],
					'difficulty' => $k[3],
					'page_url'   => $k[4],
					'status'     => 'active',
					'notes'      => 'نمونه کیورد ریسرچ',
				)
			);
			if ( is_wp_error( $id ) ) {
				continue;
			}
			$kw_ids[] = $id;
			foreach ( $k[5] as $ri => $pos ) {
				WBSS_DB::save_rank(
					array(
						'keyword_id' => $id,
						'position'   => $pos,
						'checked_at' => $g( $offsets[ $ri ] ),
						'page_url'   => $k[4],
					)
				);
			}
		}

		$contents = array(
			array( 'راهنمای نصب افزونه نوبت من', 'https://webakery.ir/blog/nobat-install', $kw_ids[1] ?? 0, 1680, 'published', 20 ),
			array( 'چک‌لیست سئو تکنیکال وردپرس', 'https://webakery.ir/blog/tech-seo', $kw_ids[5] ?? 0, 2140, 'published', 12 ),
			array( 'مقایسه درگاه‌های پرداخت ایرانی', 'https://webakery.ir/blog/gateways', $kw_ids[6] ?? 0, 1320, 'updated', 5 ),
			array( 'چطور چت آنلاین نرخ تبدیل را بالا می‌برد', 'https://webakery.ir/blog/chat-cvr', $kw_ids[3] ?? 0, 980, 'draft', 0 ),
		);
		foreach ( $contents as $c ) {
			WBSS_DB::save_content(
				array(
					'project_id'   => $pid,
					'title'        => $c[0],
					'url'          => $c[1],
					'keyword_id'   => $c[2],
					'word_count'   => $c[3],
					'status'       => $c[4],
					'published_at' => 'draft' === $c[4] ? '' : $g( $c[5] ),
				)
			);
		}

		$tech = array(
			array( 'نقشه سایت XML و ایندکس گوگل', 'index', 'high', 'done', 'https://webakery.ir/sitemap.xml' ),
			array( 'بهبود LCP صفحه اصلی', 'speed', 'critical', 'in_progress', 'https://webakery.ir' ),
			array( 'اسکیما محصول برای افزونه‌ها', 'schema', 'medium', 'open', 'https://webakery.ir' ),
			array( 'نسخه موبایل فرم پرداخت', 'mobile', 'high', 'done', 'https://webakery.ir/checkout' ),
			array( 'صفحات یتیم بلاگ', 'crawl', 'low', 'open', 'https://webakery.ir/blog' ),
		);
		foreach ( $tech as $t ) {
			WBSS_DB::save_technical(
				array(
					'project_id' => $pid,
					'title'      => $t[0],
					'category'   => $t[1],
					'severity'   => $t[2],
					'status'     => $t[3],
					'page_url'   => $t[4],
				)
			);
		}

		$bls = array(
			array( 'https://example-news.ir/best-wp-plugins', 'https://webakery.ir', 'افزونه وردپرس', 'dofollow', 42, 'live', 18 ),
			array( 'https://dev-forum.ir/thread/nobat', 'https://webakery.ir/nobat-man', 'رزرو نوبت', 'dofollow', 28, 'live', 9 ),
			array( 'https://shop-mag.ir/woocommerce-tools', 'https://webakery.ir/hesabdar', 'حسابداری فروشگاه', 'nofollow', 35, 'live', 6 ),
			array( 'https://old-blog.ir/review', 'https://webakery.ir', 'وب‌آکری', 'dofollow', 19, 'lost', 30 ),
		);
		foreach ( $bls as $b ) {
			WBSS_DB::save_backlink(
				array(
					'project_id'  => $pid,
					'source_url'  => $b[0],
					'target_url'  => $b[1],
					'anchor'      => $b[2],
					'rel_type'    => $b[3],
					'da'          => $b[4],
					'status'      => $b[5],
					'acquired_at' => $g( $b[6] ),
				)
			);
		}

		$press = array(
			array( 'دیجیاتو', 'https://digiato.example/webakery', 'https://webakery.ir', 'معرفی ابزارهای وردپرس ایرانی', 8500000, 'published', 16 ),
			array( 'زومیت', 'https://zoomit.example/seo-wp', 'https://webakery.ir/blog/seo', 'سئو برای فروشگاه‌های وردپرسی', 12000000, 'published', 4 ),
			array( 'ویرگول ویژه', '', 'https://webakery.ir/chat', 'پشتیبانی آنلاین فروشگاه', 2500000, 'planned', 0 ),
		);
		foreach ( $press as $pr ) {
			WBSS_DB::save_press(
				array(
					'project_id'   => $pid,
					'outlet'       => $pr[0],
					'article_url'  => $pr[1],
					'target_url'   => $pr[2],
					'topic'        => $pr[3],
					'cost'         => $pr[4],
					'status'       => $pr[5],
					'publish_date' => 'planned' === $pr[5] ? '' : $g( $pr[6] ),
					'follow_type'  => 'dofollow',
				)
			);
		}

		update_option( 'wbss_seeded', 1, false );
		$s                      = get_option( 'wbss_settings', array() );
		$s['default_project']   = $pid;
		update_option( 'wbss_settings', $s, false );
		return $pid;
	}
}
