<?php
defined( 'ABSPATH' ) || exit;

/**
 * درخت محتوا و امتیاز نسبی سرچ — فقط از کیوردهای واقعی گوگل.
 */
class WBGS_Tree {

	/**
	 * @param string $text
	 * @return string[]
	 */
	public static function tokens( $text ) {
		$text = WBGS_Suggest::normalize_seed( $text );
		if ( $text === '' ) {
			return array();
		}
		return preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
	}

	/**
	 * مسیر شاخه نسبت به عبارت پایه.
	 *
	 * @param string $seed
	 * @param string $keyword
	 * @return string[]
	 */
	public static function branch_path( $seed, $keyword ) {
		$seed_tok = self::tokens( $seed );
		$key_tok  = self::tokens( $keyword );
		if ( ! $key_tok ) {
			return array();
		}
		if ( $seed_tok && $key_tok === $seed_tok ) {
			return array();
		}

		$sn = count( $seed_tok );
		if ( $sn && count( $key_tok ) >= $sn && array_slice( $key_tok, 0, $sn ) === $seed_tok ) {
			return array_values( array_slice( $key_tok, $sn ) );
		}
		if ( $sn && count( $key_tok ) >= $sn && array_slice( $key_tok, -$sn ) === $seed_tok ) {
			return array_values( array_slice( $key_tok, 0, count( $key_tok ) - $sn ) );
		}

		if ( $sn ) {
			$filtered = array();
			foreach ( $key_tok as $tok ) {
				if ( ! in_array( $tok, $seed_tok, true ) ) {
					$filtered[] = $tok;
				}
			}
			if ( $filtered ) {
				return $filtered;
			}
		}

		return $key_tok;
	}

	/**
	 * امتیاز ترکیبی از دادهٔ خودِ گوگل (relevance + رتبه + تکرار).
	 *
	 * @param array{relevance?:int,rank?:int,count?:int} $row
	 * @return int
	 */
	public static function score( $row ) {
		$row        = is_array( $row ) ? $row : array();
		$relevance  = isset( $row['relevance'] ) ? (int) $row['relevance'] : 0;
		$count      = isset( $row['count'] ) ? max( 1, (int) $row['count'] ) : 1;
		$rank       = isset( $row['rank'] ) ? (int) $row['rank'] : 10;
		$rank_bonus = max( 0, 16 - $rank );
		return ( $relevance * 2 ) + ( $count * 20 ) + $rank_bonus;
	}

	/**
	 * میزان سرچ نسبی ۰–۱۰۰ داخل همین مجموعه (نه عدد ماهانهٔ Keyword Planner).
	 *
	 * @param array<int,array> $rows
	 * @return array<int,array>
	 */
	public static function with_volume( $rows ) {
		$max = 1;
		foreach ( $rows as $row ) {
			$max = max( $max, self::score( $row ) );
		}
		$out = array();
		foreach ( $rows as $row ) {
			$row['volume'] = (int) round( 100 * self::score( $row ) / $max );
			$out[]         = $row;
		}
		return $out;
	}

	/**
	 * @param string           $seed
	 * @param array<int,array> $rows  هر ردیف: text, relevance?, rank?, count?
	 * @return array
	 */
	public static function build( $seed, $rows ) {
		$seed = WBGS_Suggest::normalize_seed( $seed );
		$rows = self::with_volume( is_array( $rows ) ? $rows : array() );
		$root = self::empty_node( $seed ? $seed : 'ریشه' );

		foreach ( $rows as $row ) {
			$text = isset( $row['text'] ) ? (string) $row['text'] : '';
			if ( $text === '' ) {
				continue;
			}
			$path = self::branch_path( $seed, $text );
			$node = &$root;
			if ( ! $path ) {
				$root['leaves'][] = $row;
				unset( $node );
				continue;
			}
			foreach ( $path as $i => $tok ) {
				if ( ! isset( $node['children'][ $tok ] ) ) {
					$node['children'][ $tok ] = self::empty_node( $tok );
				}
				if ( $i === count( $path ) - 1 ) {
					$node['children'][ $tok ]['leaves'][] = $row;
				} else {
					$node = &$node['children'][ $tok ];
				}
			}
			unset( $node );
		}

		self::rollup( $root );
		return $root;
	}

	/**
	 * @return array
	 */
	private static function empty_node( $label ) {
		return array(
			'label'    => $label,
			'leaves'   => array(),
			'children' => array(),
			'volume'   => 0,
			'count'    => 0,
		);
	}

	/**
	 * @param array $node
	 */
	private static function rollup( &$node ) {
		$count  = count( $node['leaves'] );
		$volume = 0;
		foreach ( $node['leaves'] as $leaf ) {
			$volume = max( $volume, isset( $leaf['volume'] ) ? (int) $leaf['volume'] : 0 );
		}
		foreach ( $node['children'] as $key => $child ) {
			self::rollup( $node['children'][ $key ] );
			$count += (int) $node['children'][ $key ]['count'];
			$volume = max( $volume, (int) $node['children'][ $key ]['volume'] );
		}
		$node['count']  = $count;
		$node['volume'] = $volume;
	}

	/**
	 * پیلار (عبارت پایه) + کلاسترهای موضوعی از همان کیوردهای گوگل.
	 *
	 * @param string           $seed
	 * @param array<int,array> $rows
	 * @return array{pillar:string,clusters:array<int,array>}
	 */
	public static function clusters( $seed, $rows ) {
		$rows = WBGS_Intent::attach( $rows );
		$tree = self::build( $seed, $rows );
		$out  = array();
		foreach ( $tree['children'] as $label => $child ) {
			$leaves = self::flatten_leaves( $child );
			$intents = array();
			$searches = 0;
			foreach ( $leaves as $leaf ) {
				$key = isset( $leaf['intent'] ) ? $leaf['intent'] : WBGS_Intent::classify( $leaf['text'] );
				if ( ! isset( $intents[ $key ] ) ) {
					$intents[ $key ] = 0;
				}
				$intents[ $key ]++;
				$searches += isset( $leaf['searches'] ) ? (int) $leaf['searches'] : 0;
			}
			arsort( $intents );
			$top = $intents ? (string) array_key_first( $intents ) : WBGS_Intent::COMMERCIAL;
			$labels = WBGS_Intent::labels();
			$out[]  = array(
				'name'       => $label,
				'count'      => count( $leaves ),
				'searches'   => $searches,
				'intent'     => $top,
				'intent_fa'  => isset( $labels[ $top ] ) ? $labels[ $top ] : '',
				'keywords'   => $leaves,
			);
		}
		usort(
			$out,
			function ( $a, $b ) {
				if ( $a['searches'] === $b['searches'] ) {
					return $b['count'] - $a['count'];
				}
				return $b['searches'] - $a['searches'];
			}
		);
		return array(
			'pillar'   => WBGS_Suggest::normalize_seed( $seed ),
			'clusters' => $out,
		);
	}

	/**
	 * @param array $node
	 * @return array<int,array>
	 */
	public static function flatten_leaves( $node ) {
		$out = isset( $node['leaves'] ) ? $node['leaves'] : array();
		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			foreach ( $node['children'] as $child ) {
				$out = array_merge( $out, self::flatten_leaves( $child ) );
			}
		}
		return $out;
	}
}
