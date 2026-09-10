<?php
defined( 'ABSPATH' ) || exit;

/**
 * خروجی‌های ایستگاه کار از عبارت واقعی سجست: XMind، تقویم، گزارش HTML.
 */
class WBGS_Export {

	/**
	 * فایل .xmind از درخت واقعی عبارت‌ها.
	 *
	 * @param string           $seed
	 * @param array<int,array> $rows
	 * @return array{ok:bool,error:string,bin:string,name:string}
	 */
	public static function xmind( $seed, $rows ) {
		$seed = WBGS_Suggest::normalize_seed( $seed );
		if ( $seed === '' || ! class_exists( 'ZipArchive' ) ) {
			return array(
				'ok'    => false,
				'error' => class_exists( 'ZipArchive' ) ? 'empty' : 'zip',
				'bin'   => '',
				'name'  => '',
			);
		}
		$tree = WBGS_Tree::build( $seed, $rows );
		$n    = 0;
		$root = self::xmind_topic( $tree, $n );
		$sheet = array(
			'id'            => 'sheet-1',
			'class'         => 'sheet',
			'title'         => $seed,
			'rootTopic'     => $root,
			'extensions'    => array(),
			'theme'         => 'primary',
		);
		$root['structureClass'] = 'org.xmind.ui.map.unbalanced';
		$sheet['rootTopic']     = $root;

		$content  = self::json( array( $sheet ) );
		$meta     = self::json(
			array(
				'creator'              => array(
					'name'    => 'سجست‌یاب گوگل',
					'version' => defined( 'WBGS_VERSION' ) ? WBGS_VERSION : '1.3.2',
				),
				'dataStructureVersion' => '2',
			)
		);
		$manifest = self::json(
			array(
				'file-entries' => array(
					'content.json'  => new stdClass(),
					'metadata.json' => new stdClass(),
				),
			)
		);
		if ( ! $content || ! $meta || ! $manifest ) {
			return array( 'ok' => false, 'error' => 'json', 'bin' => '', 'name' => '' );
		}

		$tmp = tempnam( sys_get_temp_dir(), 'wbgs' );
		if ( ! $tmp ) {
			return array( 'ok' => false, 'error' => 'tmp', 'bin' => '', 'name' => '' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			@unlink( $tmp );
			return array( 'ok' => false, 'error' => 'zip', 'bin' => '', 'name' => '' );
		}
		$zip->addFromString( 'content.json', $content );
		$zip->addFromString( 'metadata.json', $meta );
		$zip->addFromString( 'manifest.json', $manifest );
		$zip->close();
		$bin = (string) file_get_contents( $tmp );
		@unlink( $tmp );
		$slug = preg_replace( '/[^\p{L}\p{N}\-_]+/u', '-', $seed );
		$slug = trim( (string) $slug, '-' );
		if ( $slug === '' ) {
			$slug = 'sajest';
		}
		return array(
			'ok'    => true,
			'error' => '',
			'bin'   => $bin,
			'name'  => $slug . '.xmind',
		);
	}

	/**
	 * @param array $node
	 * @param int   $n
	 * @return array<string,mixed>
	 */
	private static function xmind_topic( $node, &$n ) {
		$n++;
		$topic = array(
			'id'    => 't' . $n,
			'class' => 'topic',
			'title' => isset( $node['label'] ) ? (string) $node['label'] : '',
		);
		$kids = array();
		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			foreach ( $node['children'] as $child ) {
				$kids[] = self::xmind_topic( $child, $n );
			}
		}
		if ( ! empty( $node['leaves'] ) && is_array( $node['leaves'] ) ) {
			foreach ( $node['leaves'] as $leaf ) {
				$text = isset( $leaf['text'] ) ? WBGS_Suggest::normalize_seed( $leaf['text'] ) : '';
				if ( $text === '' || $text === $topic['title'] ) {
					continue;
				}
				$n++;
				$kids[] = array(
					'id'    => 't' . $n,
					'class' => 'topic',
					'title' => $text,
				);
			}
		}
		if ( $kids ) {
			$topic['children'] = array( 'attached' => $kids );
		}
		return $topic;
	}

	/**
	 * تقویم محتوا از بریف‌های واقعی — هفته‌بندی کلاسترها.
	 *
	 * @param string           $seed
	 * @param array<int,array> $rows
	 * @return array<int,array>
	 */
	public static function calendar( $seed, $rows ) {
		$briefs = WBGS_Work::briefs( $seed, $rows );
		$weeks  = array();
		foreach ( $briefs as $i => $brief ) {
			$week = (int) floor( $i / 3 ) + 1;
			if ( ! isset( $weeks[ $week ] ) ) {
				$weeks[ $week ] = array(
					'week'  => $week,
					'title' => 'هفتهٔ ' . self::fa_num( $week ),
					'items' => array(),
				);
			}
			$weeks[ $week ]['items'][] = array(
				'kind'      => ! empty( $brief['is_pillar'] ) ? 'پیلار' : 'کلاستر',
				'cluster'   => isset( $brief['cluster'] ) ? $brief['cluster'] : '',
				'h1'        => isset( $brief['h1'] ) ? $brief['h1'] : '',
				'intent_fa' => isset( $brief['intent_fa'] ) ? $brief['intent_fa'] : '',
				'count'     => isset( $brief['count'] ) ? (int) $brief['count'] : 0,
			);
		}
		return array_values( $weeks );
	}

	/**
	 * گزارش HTML سفید برای مشتری.
	 *
	 * @param string           $seed
	 * @param array<int,array> $rows
	 * @return string
	 */
	public static function html_report( $seed, $rows ) {
		$seed     = WBGS_Suggest::normalize_seed( $seed );
		$clusters = WBGS_Tree::clusters( $seed, $rows );
		$cal      = self::calendar( $seed, $rows );
		$count    = count( $rows );
		$esc      = function ( $t ) {
			return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' );
		};
		$html     = '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8" />';
		$html    .= '<title>' . $esc( 'گزارش سجست‌یاب — ' . $seed ) . '</title>';
		$html    .= '<style>body{font-family:Tahoma,Vazirmatn,sans-serif;max-width:880px;margin:24px auto;padding:0 16px;color:#202124}h1,h2{margin:18px 0 8px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #dadce0;padding:6px 8px;text-align:right} .note{color:#5f6368;font-size:13px}</style></head><body>';
		$html    .= '<h1>' . $esc( 'گزارش کیورد — ' . $seed ) . '</h1>';
		$html    .= '<p class="note">منبع: پیشنهادهای واقعی Autocomplete گوگل / یوتیوب. حجم ماهانه ساخته نشده. سازنده: webakery.ir</p>';
		$html    .= '<p>تعداد عبارت: ' . $esc( (string) $count ) . ' — کلاستر: ' . $esc( (string) count( $clusters['clusters'] ) ) . '</p>';
		if ( class_exists( 'WBGS_Entity' ) ) {
			$shelf  = WBGS_Entity::shelf( $rows );
			$matrix = WBGS_Entity::matrix( $rows );
			$faq    = WBGS_Entity::faq_rows( $rows );
			$ilabels = class_exists( 'WBGS_Intent' ) ? WBGS_Intent::labels() : array();
			$html   .= '<h2>قفسه کالا</h2><table><tr><th>سطل</th><th>تعداد</th></tr>';
			foreach ( $shelf as $bucket ) {
				$html .= '<tr><td>' . $esc( $bucket['fa'] ) . '</td><td>' . $esc( (string) $bucket['count'] ) . '</td></tr>';
			}
			$html .= '</table><h2>ماتریس موجودیت و اینتنت</h2><table><tr><th>سطل</th>';
			foreach ( $matrix['intents'] as $ik ) {
				$html .= '<th>' . $esc( isset( $ilabels[ $ik ] ) ? $ilabels[ $ik ] : $ik ) . '</th>';
			}
			$html .= '<th>جمع</th></tr>';
			foreach ( WBGS_Entity::keys() as $ek ) {
				$html .= '<tr><td>' . $esc( WBGS_Entity::labels()[ $ek ] ) . '</td>';
				foreach ( $matrix['intents'] as $ik ) {
					$html .= '<td>' . $esc( (string) $matrix['cells'][ $ek ][ $ik ] ) . '</td>';
				}
				$html .= '<td>' . $esc( (string) $matrix['cells'][ $ek ]['total'] ) . '</td></tr>';
			}
			$html .= '</table><h2>پرسش‌های محتوا (FAQ)</h2>';
			if ( $faq ) {
				$html .= '<ul>';
				foreach ( $faq as $qrow ) {
					$html .= '<li>' . $esc( isset( $qrow['text'] ) ? $qrow['text'] : '' ) . '</li>';
				}
				$html .= '</ul><p class="note">فقط عبارت واقعی گوگل. مقاله ساخته نشده.</p>';
			} else {
				$html .= '<p class="note">پرسش واقعی در این استخراج نبود.</p>';
			}
		}
		$html    .= '<h2>پیلار کلاستر</h2><table><tr><th>کلاستر</th><th>تعداد</th><th>اینتنت</th></tr>';
		foreach ( $clusters['clusters'] as $c ) {
			$html .= '<tr><td>' . $esc( $c['name'] ) . '</td><td>' . $esc( (string) $c['count'] ) . '</td><td>' . $esc( $c['intent_fa'] ) . '</td></tr>';
		}
		$html .= '</table><h2>تقویم محتوا</h2>';
		foreach ( $cal as $week ) {
			$html .= '<h3>' . $esc( $week['title'] ) . '</h3><ul>';
			foreach ( $week['items'] as $it ) {
				$html .= '<li>' . $esc( $it['kind'] . ' — ' . $it['h1'] ) . '</li>';
			}
			$html .= '</ul>';
		}
		$html .= '<h2>عبارت‌ها</h2><ol>';
		foreach ( array_slice( $rows, 0, 200 ) as $row ) {
			$text = isset( $row['text'] ) ? $row['text'] : '';
			if ( $text !== '' ) {
				$html .= '<li>' . $esc( $text ) . '</li>';
			}
		}
		$html .= '</ol></body></html>';
		return $html;
	}

	/**
	 * @param int $n
	 * @return string
	 */
	/**
	 * @param mixed $data
	 * @return string
	 */
	private static function json( $data ) {
		$json = json_encode( $data, JSON_UNESCAPED_UNICODE );
		return is_string( $json ) ? $json : '';
	}

	private static function fa_num( $n ) {
		$map = array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' );
		return strtr( (string) (int) $n, $map );
	}
}
