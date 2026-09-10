<?php
defined( 'ABSPATH' ) || exit;

/**
 * ایستگاه کار سئو از دادهٔ واقعی سجست — بدون حجم ساختگی و بدون KD تقلبی.
 */
class WBGS_Work {

	/**
	 * نشانه‌های سوالی داخل خودِ عبارت گوگل.
	 *
	 * @return string[]
	 */
	public static function question_markers() {
		$marks = array(
			'چیست',
			'چیه',
			'چگونه',
			'چطور',
			'چرا',
			'یعنی',
			'معنی',
			'تعریف',
			'آیا',
			'what',
			'how',
			'why',
			'which',
			'when',
			'where',
		);
		if ( class_exists( 'WBGS_Affixes' ) ) {
			$groups = WBGS_Affixes::groups();
			if ( isset( $groups['question']['markers'] ) ) {
				$marks = array_values( array_unique( array_merge( $marks, (array) $groups['question']['markers'] ) ) );
			}
		}
		return $marks;
	}

	/**
	 * @param string $text
	 * @return bool
	 */
	public static function is_question( $text ) {
		$text = WBGS_Suggest::normalize_seed( $text );
		if ( $text === '' ) {
			return false;
		}
		if ( false !== mb_strpos( $text, '؟' ) || false !== mb_strpos( $text, '?' ) ) {
			return true;
		}
		foreach ( self::question_markers() as $mark ) {
			if ( preg_match( '/' . preg_quote( $mark, '/' ) . '/ui', $text ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * امتیاز رقابت نسبی ۱–۱۰۰ از خودِ سجست (رتبه/relevance + اینتنت + طول عبارت).
	 * KD اهرفس یا رقابت Keyword Planner نیست.
	 *
	 * @param array $row
	 * @return int
	 */
	public static function competition( $row ) {
		$row       = is_array( $row ) ? $row : array();
		$text      = isset( $row['text'] ) ? (string) $row['text'] : '';
		$relevance = isset( $row['relevance'] ) ? (int) $row['relevance'] : 0;
		$rank      = isset( $row['rank'] ) ? (int) $row['rank'] : 10;
		$count     = isset( $row['count'] ) ? max( 1, (int) $row['count'] ) : 1;
		$words     = WBGS_Suggest::word_count( $text );
		$intent    = isset( $row['intent'] ) ? (string) $row['intent'] : WBGS_Intent::classify( $text );

		$visibility = $relevance > 0
			? min( 50, (int) round( $relevance / 20 ) )
			: max( 0, 40 - ( $rank * 3 ) );

		$intent_pts = 22;
		if ( WBGS_Intent::TRANSACTIONAL === $intent ) {
			$intent_pts = 30;
		} elseif ( WBGS_Intent::COMMERCIAL === $intent ) {
			$intent_pts = 22;
		} elseif ( WBGS_Intent::NAVIGATIONAL === $intent ) {
			$intent_pts = 16;
		} elseif ( WBGS_Intent::INFORMATIONAL === $intent ) {
			$intent_pts = 8;
		}

		if ( $words <= 1 ) {
			$length_pts = 20;
		} elseif ( 2 === $words ) {
			$length_pts = 14;
		} elseif ( 3 === $words ) {
			$length_pts = 8;
		} else {
			$length_pts = 2;
		}

		$repeat_pts = min( 10, ( $count - 1 ) * 3 );
		$n          = $visibility + $intent_pts + $length_pts + $repeat_pts;
		return max( 1, min( 100, (int) $n ) );
	}

	/**
	 * @param int $score
	 * @return array{key:string,fa:string}
	 */
	public static function competition_band( $score ) {
		$score = (int) $score;
		if ( $score >= 67 ) {
			return array( 'key' => 'high', 'fa' => 'بالا' );
		}
		if ( $score >= 34 ) {
			return array( 'key' => 'mid', 'fa' => 'متوسط' );
		}
		return array( 'key' => 'low', 'fa' => 'پایین' );
	}

	/**
	 * نام کلاستر از مسیر شاخهٔ همان عبارت گوگل.
	 *
	 * @param string $seed
	 * @param string $keyword
	 * @return string
	 */
	public static function cluster_name( $seed, $keyword ) {
		$path = WBGS_Tree::branch_path( $seed, $keyword );
		if ( ! $path ) {
			return WBGS_Suggest::normalize_seed( $seed );
		}
		return (string) $path[0];
	}

	/**
	 * عنوان/H1 فقط از یک عبارت واقعی گوگل — هیچ کلمه‌ای ساخته نمی‌شود.
	 *
	 * @param string[] $phrases
	 * @return string
	 */
	public static function pick_title( $phrases ) {
		$best       = '';
		$best_score = -1;
		foreach ( (array) $phrases as $phrase ) {
			$phrase = WBGS_Suggest::normalize_seed( $phrase );
			if ( $phrase === '' ) {
				continue;
			}
			$n   = WBGS_Suggest::word_count( $phrase );
			$len = function_exists( 'mb_strlen' ) ? mb_strlen( $phrase ) : strlen( $phrase );
			if ( $n < 2 || $n > 8 || $len > 70 ) {
				continue;
			}
			if ( WBGS_Intent::NAVIGATIONAL === WBGS_Intent::classify( $phrase ) ) {
				continue;
			}
			$score = 100 - abs( 40 - $len );
			if ( self::is_question( $phrase ) ) {
				$score -= 12;
			}
			if ( $score > $best_score ) {
				$best_score = $score;
				$best       = $phrase;
			}
		}
		if ( $best !== '' ) {
			return $best;
		}
		foreach ( (array) $phrases as $phrase ) {
			$phrase = WBGS_Suggest::normalize_seed( $phrase );
			if ( $phrase !== '' ) {
				return $phrase;
			}
		}
		return '';
	}

	/**
	 * متا فقط با چسباندن عبارت‌های واقعی گوگل.
	 *
	 * @param string[] $phrases
	 * @param string   $title
	 * @return string
	 */
	public static function pick_meta( $phrases, $title = '' ) {
		$title = WBGS_Suggest::normalize_seed( $title );
		$parts = array();
		foreach ( (array) $phrases as $phrase ) {
			$phrase = WBGS_Suggest::normalize_seed( $phrase );
			if ( $phrase === '' || $phrase === $title ) {
				continue;
			}
			$parts[]  = $phrase;
			$joined   = implode( '، ', $parts );
			$len      = function_exists( 'mb_strlen' ) ? mb_strlen( $joined ) : strlen( $joined );
			if ( $len >= 120 || count( $parts ) >= 3 ) {
				break;
			}
		}
		$joined = implode( '، ', $parts );
		$len    = function_exists( 'mb_strlen' ) ? mb_strlen( $joined ) : strlen( $joined );
		if ( $len > 160 ) {
			$joined = ( function_exists( 'mb_substr' ) ? mb_substr( $joined, 0, 157 ) : substr( $joined, 0, 157 ) ) . '…';
		}
		return $joined;
	}

	/**
	 * @param string $seed
	 * @param string $name
	 * @param array  $cluster
	 * @return array
	 */
	public static function brief_for_cluster( $seed, $name, $cluster ) {
		$cluster  = is_array( $cluster ) ? $cluster : array();
		$keywords = isset( $cluster['keywords'] ) && is_array( $cluster['keywords'] ) ? $cluster['keywords'] : array();
		$phrases  = array();
		$questions = array();
		$comps    = array();
		foreach ( $keywords as $row ) {
			$text = isset( $row['text'] ) ? WBGS_Suggest::normalize_seed( $row['text'] ) : '';
			if ( $text === '' ) {
				continue;
			}
			$phrases[] = $text;
			if ( self::is_question( $text ) ) {
				$questions[] = $text;
			}
			$comps[] = self::competition( $row );
		}
		$title = self::pick_title( $phrases );
		$meta  = self::pick_meta( $phrases, $title );
		$h2    = array();
		foreach ( $questions as $q ) {
			if ( $q !== $title ) {
				$h2[] = $q;
			}
		}
		foreach ( $phrases as $phrase ) {
			if ( $phrase === $title || in_array( $phrase, $h2, true ) ) {
				continue;
			}
			$h2[] = $phrase;
			if ( count( $h2 ) >= 8 ) {
				break;
			}
		}
		$avg  = $comps ? (int) round( array_sum( $comps ) / count( $comps ) ) : 0;
		$band = self::competition_band( $avg );
		$labels = WBGS_Intent::labels();
		$intent = isset( $cluster['intent'] ) ? (string) $cluster['intent'] : WBGS_Intent::COMMERCIAL;
		return array(
			'cluster'             => $name,
			'pillar'              => WBGS_Suggest::normalize_seed( $seed ),
			'is_pillar'           => false,
			'h1'                  => $title,
			'h2'                  => $h2,
			'questions'           => $questions,
			'title'               => $title,
			'meta'                => $meta,
			'intent'              => $intent,
			'intent_fa'           => isset( $cluster['intent_fa'] ) ? (string) $cluster['intent_fa'] : ( isset( $labels[ $intent ] ) ? $labels[ $intent ] : '' ),
			'count'               => count( $phrases ),
			'competition'         => $avg,
			'competition_band'    => $band['key'],
			'competition_band_fa' => $band['fa'],
			'note'                => 'H1 و H2 فقط از پیشنهادهای واقعی گوگل هستند. امتیاز رقابت نسبی KD اهرفس نیست.',
		);
	}

	/**
	 * بریف صفحهٔ ستون از عبارت‌های واقعی همان استخراج.
	 *
	 * @param string           $seed
	 * @param array<int,array> $rows
	 * @return array
	 */
	public static function pillar_brief( $seed, $rows ) {
		$seed    = WBGS_Suggest::normalize_seed( $seed );
		$phrases = array();
		foreach ( (array) $rows as $row ) {
			$text = isset( $row['text'] ) ? WBGS_Suggest::normalize_seed( $row['text'] ) : '';
			if ( $text !== '' ) {
				$phrases[] = $text;
			}
		}
		$h1 = in_array( $seed, $phrases, true ) ? $seed : self::pick_title( $phrases );
		$clusters = WBGS_Tree::clusters( $seed, $rows );
		$h2       = array();
		foreach ( $clusters['clusters'] as $cluster ) {
			$cphrases = array();
			foreach ( $cluster['keywords'] as $kw ) {
				if ( ! empty( $kw['text'] ) ) {
					$cphrases[] = $kw['text'];
				}
			}
			$picked = self::pick_title( $cphrases );
			if ( $picked !== '' && $picked !== $h1 && ! in_array( $picked, $h2, true ) ) {
				$h2[] = $picked;
			}
			if ( count( $h2 ) >= 10 ) {
				break;
			}
		}
		$questions = array();
		$comps     = array();
		foreach ( (array) $rows as $row ) {
			$text = isset( $row['text'] ) ? (string) $row['text'] : '';
			if ( self::is_question( $text ) ) {
				$questions[] = WBGS_Suggest::normalize_seed( $text );
			}
			$comps[] = self::competition( $row );
		}
		$avg  = $comps ? (int) round( array_sum( $comps ) / count( $comps ) ) : 0;
		$band = self::competition_band( $avg );
		return array(
			'cluster'             => $seed,
			'pillar'              => $seed,
			'is_pillar'           => true,
			'h1'                  => $h1,
			'h2'                  => $h2,
			'questions'           => array_values( array_unique( $questions ) ),
			'title'               => $h1,
			'meta'                => self::pick_meta( $phrases, $h1 ),
			'intent'              => WBGS_Intent::COMMERCIAL,
			'intent_fa'           => WBGS_Intent::labels()[ WBGS_Intent::COMMERCIAL ],
			'count'               => count( $phrases ),
			'competition'         => $avg,
			'competition_band'    => $band['key'],
			'competition_band_fa' => $band['fa'],
			'note'                => 'H1 و H2 فقط از پیشنهادهای واقعی گوگل هستند. امتیاز رقابت نسبی KD اهرفس نیست.',
		);
	}

	/**
	 * @param string           $seed
	 * @param array<int,array> $rows
	 * @return array<int,array>
	 */
	public static function briefs( $seed, $rows ) {
		$rows     = is_array( $rows ) ? $rows : array();
		$rows     = WBGS_Intent::attach( $rows );
		$clusters = WBGS_Tree::clusters( $seed, $rows );
		$out      = array( self::pillar_brief( $seed, $rows ) );
		foreach ( $clusters['clusters'] as $cluster ) {
			$out[] = self::brief_for_cluster( $seed, $cluster['name'], $cluster );
		}
		return $out;
	}

	/**
	 * @param string           $seed
	 * @param array<int,array> $rows
	 * @return array<int,array>
	 */
	public static function enrich_all( $seed, $rows ) {
		$seed     = WBGS_Suggest::normalize_seed( $seed );
		$rows     = WBGS_Intent::attach( is_array( $rows ) ? $rows : array() );
		$rows     = WBGS_Tree::with_volume( $rows );
		$clusters = WBGS_Tree::clusters( $seed, $rows );
		$owned    = array();
		$titles   = array();
		$metas    = array();
		foreach ( $clusters['clusters'] as $cluster ) {
			$brief = self::brief_for_cluster( $seed, $cluster['name'], $cluster );
			foreach ( $cluster['keywords'] as $kw ) {
				$text = isset( $kw['text'] ) ? (string) $kw['text'] : '';
				if ( $text === '' ) {
					continue;
				}
				$owned[ $text ]  = $cluster['name'];
				$titles[ $text ] = $brief['title'];
				$metas[ $text ]  = $brief['meta'];
			}
		}
		$pillar_brief = self::pillar_brief( $seed, $rows );
		$out          = array();
		foreach ( $rows as $row ) {
			$text = isset( $row['text'] ) ? (string) $row['text'] : '';
			if ( $text === '' ) {
				continue;
			}
			$tax                          = WBGS_Taxonomy::classify( $seed, $text );
			$row                          = array_merge( $row, $tax );
			$row['words']                 = WBGS_Suggest::word_count( $text );
			$row['longtail']              = WBGS_Suggest::is_longtail( $text );
			$row['question']              = self::is_question( $text );
			$row['cluster']               = isset( $owned[ $text ] ) ? $owned[ $text ] : $seed;
			$row['pillar']                = $seed;
			$row['suggest_score']         = WBGS_Tree::score( $row );
			$row['relative_competition']  = self::competition( $row );
			$band                         = self::competition_band( $row['relative_competition'] );
			$row['competition_band']      = $band['key'];
			$row['competition_band_fa']   = $band['fa'];
			$row['title']                 = isset( $titles[ $text ] ) ? $titles[ $text ] : $pillar_brief['title'];
			$row['meta']                  = isset( $metas[ $text ] ) ? $metas[ $text ] : $pillar_brief['meta'];
			$out[]                        = $row;
		}
		return $out;
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function csv_escape( $value ) {
		$value = (string) ( null === $value ? '' : $value );
		if ( preg_match( '/[",\n\r]/', $value ) ) {
			return '"' . str_replace( '"', '""', $value ) . '"';
		}
		return $value;
	}

	/**
	 * @return string[]
	 */
	public static function csv_header() {
		return array(
			'keyword',
			'words',
			'length',
			'intent',
			'longtail',
			'question',
			'geo',
			'seasonal',
			'lsi',
			'branded',
			'affixes',
			'cluster',
			'pillar',
			'suggest_relevance',
			'suggest_rank',
			'suggest_score',
			'relative_competition',
			'competition_band',
			'monthly_searches',
			'title',
			'meta',
		);
	}

	/**
	 * CSV با BOM برای اکسل و ستون‌های سئوکار.
	 *
	 * @param string           $seed
	 * @param array<int,array> $rows
	 * @return string
	 */
	public static function csv_document( $seed, $rows ) {
		$enriched = self::enrich_all( $seed, $rows );
		$lines    = array( implode( ',', self::csv_header() ) );
		foreach ( $enriched as $row ) {
			$searches = array_key_exists( 'searches', $row ) && null !== $row['searches'] && '' !== $row['searches']
				? (string) (int) $row['searches']
				: '';
			$lines[]  = implode(
				',',
				array(
					self::csv_escape( $row['text'] ),
					(string) (int) $row['words'],
					self::csv_escape( isset( $row['length_fa'] ) ? $row['length_fa'] : '' ),
					self::csv_escape( isset( $row['intent_fa'] ) ? $row['intent_fa'] : $row['intent'] ),
					! empty( $row['longtail'] ) ? '1' : '0',
					! empty( $row['question'] ) ? '1' : '0',
					! empty( $row['geo'] ) ? '1' : '0',
					! empty( $row['seasonal'] ) ? '1' : '0',
					! empty( $row['lsi'] ) ? '1' : '0',
					! empty( $row['branded'] ) ? '1' : '0',
					self::csv_escape( isset( $row['affix_fa'] ) ? $row['affix_fa'] : '' ),
					self::csv_escape( $row['cluster'] ),
					self::csv_escape( $row['pillar'] ),
					(string) (int) ( isset( $row['relevance'] ) ? $row['relevance'] : 0 ),
					(string) (int) ( isset( $row['rank'] ) ? $row['rank'] : 0 ),
					(string) (int) $row['suggest_score'],
					(string) (int) $row['relative_competition'],
					self::csv_escape( $row['competition_band_fa'] ),
					$searches,
					self::csv_escape( $row['title'] ),
					self::csv_escape( $row['meta'] ),
				)
			);
		}
		return "\xEF\xBB\xBF" . implode( "\n", $lines );
	}

	/**
	 * @param string           $seed
	 * @param array<int,array> $rows
	 * @return string
	 */
	public static function brief_text( $seed, $rows ) {
		$briefs = self::briefs( $seed, $rows );
		$lines  = array(
			'بریف محتوا — ' . WBGS_Suggest::normalize_seed( $seed ),
			'منبع: فقط پیشنهادهای واقعی Autocomplete گوگل',
			'امتیاز رقابت نسبی KD اهرفس نیست؛ از رتبهٔ سجست + اینتنت + تعداد واژه ساخته می‌شود.',
			'',
		);
		foreach ( $briefs as $brief ) {
			$head    = ! empty( $brief['is_pillar'] ) ? '## پیلار: ' : '## کلاستر: ';
			$lines[] = $head . $brief['cluster'];
			$lines[] = 'H1: ' . $brief['h1'];
			$lines[] = 'عنوان: ' . $brief['title'];
			$lines[] = 'متا: ' . $brief['meta'];
			$lines[] = 'اینتنت: ' . $brief['intent_fa'];
			$lines[] = 'امتیاز رقابت نسبی: ' . $brief['competition'] . ' (' . $brief['competition_band_fa'] . ')';
			if ( ! empty( $brief['h2'] ) ) {
				$lines[] = 'H2:';
				foreach ( $brief['h2'] as $h2 ) {
					$lines[] = '- ' . $h2;
				}
			}
			if ( ! empty( $brief['questions'] ) ) {
				$lines[] = 'سوالات:';
				foreach ( $brief['questions'] as $q ) {
					$lines[] = '- ' . $q;
				}
			}
			$lines[] = '';
		}
		return implode( "\n", $lines );
	}

	/**
	 * مقایسهٔ دو مجموعه عبارت واقعی.
	 *
	 * @param array<int,array|string> $rows_a
	 * @param array<int,array|string> $rows_b
	 * @return array{both:array,only_a:array,only_b:array,stats:array}
	 */
	public static function compare( $rows_a, $rows_b ) {
		$map_a = self::text_map( $rows_a );
		$map_b = self::text_map( $rows_b );
		$both  = array();
		$only_a = array();
		$only_b = array();
		foreach ( $map_a as $text => $row ) {
			if ( isset( $map_b[ $text ] ) ) {
				$both[] = $row;
			} else {
				$only_a[] = $row;
			}
		}
		foreach ( $map_b as $text => $row ) {
			if ( ! isset( $map_a[ $text ] ) ) {
				$only_b[] = $row;
			}
		}
		return array(
			'both'   => $both,
			'only_a' => $only_a,
			'only_b' => $only_b,
			'stats'  => array(
				'a'      => count( $map_a ),
				'b'      => count( $map_b ),
				'shared' => count( $both ),
				'only_a' => count( $only_a ),
				'only_b' => count( $only_b ),
			),
		);
	}

	/**
	 * @param array<int,array|string> $rows
	 * @return array<string,array>
	 */
	private static function text_map( $rows ) {
		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( is_string( $row ) ) {
				$text = WBGS_Suggest::normalize_seed( $row );
				$row  = array( 'text' => $text );
			} else {
				$text = isset( $row['text'] ) ? WBGS_Suggest::normalize_seed( $row['text'] ) : '';
			}
			if ( $text === '' || isset( $out[ $text ] ) ) {
				continue;
			}
			$row['text'] = $text;
			$out[ $text ] = $row;
		}
		return $out;
	}
}
