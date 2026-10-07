<?php
/**
 * Builds an import plan (list of product specs) from parsed sheets + user mapping.
 *
 * Pure logic: no database writes, so the same plan powers the preview and the import.
 *
 * @package SBPI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Planner.
 */
final class SBPI_Planner {

	/** Column roles offered in the mapping UI. */
	public static function roles() {
		return array(
			'ignore'      => 'نادیده گرفته شود',
			'model'       => 'نام / مدل محصول (کلید گروه‌بندی)',
			'var_attr'    => 'ویژگی متغیر (ساخت تنوع)',
			'info_attr'   => 'ویژگی نمایشی (مشخصات)',
			'sku'         => 'شناسه (SKU)',
			'price'       => 'قیمت عادی',
			'sale_price'  => 'قیمت فروش ویژه',
			'stock'       => 'موجودی انبار (عدد)',
			'image'       => 'آدرس تصویر (URL)',
			'description' => 'توضیحات کامل',
			'short_desc'  => 'توضیح کوتاه',
			'category'    => 'دسته‌بندی (با > برای زیردسته)',
			'tags'        => 'برچسب‌ها (با | جدا)',
			'brand'       => 'برند',
		);
	}

	/** Known attributes: pattern => [label, latin slug, default role]. */
	const ATTR_GUESS = array(
		'/storage|حافظه|ظرفیت|هارد|capacity/iu'      => array( 'حافظه داخلی', 'storage', 'var_attr' ),
		'/colou?r|رنگ/iu'                             => array( 'رنگ', 'color', 'var_attr' ),
		'/region|منطقه|ریجن|پارت ?نامبر/iu'          => array( 'ریجن (سری منطقه‌ای)', 'region', 'var_attr' ),
		'/grade|condition|وضعیت|کیفیت|گرید/iu'        => array( 'وضعیت کالا', 'condition', 'var_attr' ),
		'/ram|رم/iu'                                  => array( 'حافظه رم', 'ram', 'var_attr' ),
		'/firmware|version|فریمور|فریمویر|نسخه/iu'   => array( 'نسخه فریمور', 'firmware', 'info_attr' ),
		'/items|اقلام|متعلقات|محتویات|پک/iu'          => array( 'اقلام همراه', 'package', 'info_attr' ),
		'/warranty|گارانتی/iu'                        => array( 'گارانتی', 'warranty', 'info_attr' ),
	);

	/** Brand detection on model / sheet text. Order matters ("Xbox … Galaxy Black"). */
	const BRANDS = array(
		'/xbox|microsoft|مایکروسافت/iu'                                      => array( 'مایکروسافت', 'microsoft', 'Microsoft' ),
		'/iphone|ipad|airpods|macbook|imac|apple ?watch|apple|اپل|آیفون/iu'   => array( 'اپل', 'apple', 'Apple' ),
		'/\bps ?[1-5]\b|play ?station|dual ?sense|dual ?shock|sony|سونی|پلی ?استیشن|پی‌?اس/iu' => array( 'سونی', 'sony', 'Sony' ),
		'/samsung|galaxy|سامسونگ/iu'                                         => array( 'سامسونگ', 'samsung', 'Samsung' ),
		'/xiaomi|redmi|poco|شیائومی/iu'                                      => array( 'شیائومی', 'xiaomi', 'Xiaomi' ),
		'/nintendo|switch|نینتندو/iu'                                        => array( 'نینتندو', 'nintendo', 'Nintendo' ),
	);

	/**
	 * Suggested mapping for a parsed sheet.
	 *
	 * @param array $sheet Parsed sheet.
	 * @return array Sheet settings.
	 */
	public static function default_settings( array $sheet ) {
		$columns   = array();
		$has_model = false;
		$grade_col = false;
		foreach ( $sheet['headers'] as $c => $header ) {
			$label = SBPI_Util::header_label( $header );
			$key   = SBPI_Util::key( $label );
			$col   = array(
				'role'  => 'info_attr',
				'name'  => $label,
				'slug'  => '',
				'split' => self::has_separator( $sheet, $c ),
			);
			if ( in_array( $key, array( 'n', '#', 'no', 'row', 'ردیف', 'شماره' ), true ) ) {
				$col['role'] = 'ignore';
			} elseif ( ! $has_model && preg_match( '/^(model|name|title|product|مدل|نام|عنوان|محصول)/iu', $key ) ) {
				$col['role'] = 'model';
				$has_model   = true;
			} elseif ( preg_match( '/sale|تخفیف|ویژه/iu', $key ) ) {
				$col['role'] = 'sale_price';
			} elseif ( preg_match( '/price|قیمت/iu', $key ) ) {
				$col['role'] = 'price';
			} elseif ( preg_match( '/^(sku|کد کالا|شناسه)/iu', $key ) ) {
				$col['role'] = 'sku';
			} elseif ( preg_match( '/stock|qty|موجودی|تعداد/iu', $key ) ) {
				$col['role'] = 'stock';
			} elseif ( preg_match( '/image|photo|تصویر|عکس/iu', $key ) ) {
				$col['role'] = 'image';
			} elseif ( preg_match( '/^(brand|برند)/iu', $key ) ) {
				$col['role'] = 'brand';
			} elseif ( preg_match( '/categor|دسته/iu', $key ) ) {
				$col['role'] = 'category';
			} elseif ( preg_match( '/^(desc|توضیح)/iu', $key ) ) {
				$col['role'] = 'description';
			} else {
				foreach ( self::ATTR_GUESS as $re => $guess ) {
					if ( preg_match( $re, $header ) ) {
						$col['name'] = $guess[0];
						$col['slug'] = $guess[1];
						$col['role'] = $guess[2];
						if ( 'condition' === $guess[1] ) {
							$grade_col = true;
						}
						break;
					}
				}
			}
			$columns[ (string) $c ] = $col;
		}

		// Section titles like "PS5 — ACCENT (اکانتی) — استوک": the middle part is a real
		// option (account type) when sections have 3 parts and a grade column exists.
		$three = 0;
		foreach ( $sheet['rows'] as $row ) {
			if ( count( $row['section'] ) >= 3 ) {
				$three++;
			}
		}
		$columns['__s1']    = array( 'role' => 'ignore', 'name' => 'سری', 'slug' => '', 'split' => false );
		$columns['__s2']    = array( 'role' => 'ignore', 'name' => 'نوع', 'slug' => 'type', 'split' => false );
		$columns['__s3']    = array( 'role' => 'ignore', 'name' => 'وضعیت کالا', 'slug' => 'condition', 'split' => false );
		$columns['__sheet'] = array( 'role' => 'ignore', 'name' => 'شیت', 'slug' => '', 'split' => false );
		if ( $sheet['rows'] && $three > count( $sheet['rows'] ) / 2 ) {
			$columns['__s2']['role'] = 'var_attr';
			if ( ! $grade_col ) {
				$columns['__s3']['role'] = 'var_attr';
			}
		}

		$category = trim( preg_replace( '/\s*(کامل|لیست|list)\s*/iu', ' ', $sheet['name'] ) );

		return array(
			'enabled'   => 'products' === $sheet['kind'] && ! empty( $sheet['rows'] ),
			'category'  => '' === $category ? $sheet['name'] : $category,
			'brand'     => '',
			'title_tpl' => '{model}',
			'columns'   => $columns,
		);
	}

	/**
	 * Does a column contain the multi-value separator?
	 *
	 * @param array $sheet Parsed sheet.
	 * @param int   $c     Column.
	 * @return bool
	 */
	private static function has_separator( array $sheet, $c ) {
		foreach ( $sheet['rows'] as $row ) {
			if ( false !== strpos( (string) $row['cells'][ $c ], '|' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Detect brand from free text.
	 *
	 * @param string $text Text.
	 * @return array|null [fa name, slug, en name]
	 */
	public static function detect_brand( $text ) {
		foreach ( self::BRANDS as $re => $brand ) {
			if ( preg_match( $re, (string) $text ) ) {
				return $brand;
			}
		}
		return null;
	}

	/**
	 * Build the plan.
	 *
	 * @param array $parsed   Parsed sheets.
	 * @param array $settings ['global' => [...], 'sheets' => [index => sheet settings]].
	 * @return array{products: array, glossary: array, warnings: string[]}
	 */
	public static function build( array $parsed, array $settings ) {
		$global   = $settings['global'];
		$sep      = '' !== $global['separator'] ? $global['separator'] : '|';
		$glossary = array();
		$notes    = array();
		foreach ( $parsed as $sheet ) {
			foreach ( $sheet['glossary'] as $g ) {
				$glossary[] = $g;
			}
			foreach ( $sheet['notes'] as $n ) {
				$notes[] = $n;
			}
		}

		$groups   = array();
		$warnings = array();
		foreach ( $parsed as $si => $sheet ) {
			$conf = isset( $settings['sheets'][ $si ] ) ? $settings['sheets'][ $si ] : null;
			if ( ! $conf || empty( $conf['enabled'] ) || 'products' !== $sheet['kind'] ) {
				continue;
			}
			$model_col = null;
			foreach ( $conf['columns'] as $ck => $col ) {
				if ( 'model' === $col['role'] ) {
					$model_col = $ck;
					break;
				}
			}
			if ( null === $model_col ) {
				$warnings[] = sprintf( 'شیت «%s»: ستون نام/مدل انتخاب نشده و نادیده گرفته شد.', $sheet['name'] );
				continue;
			}

			foreach ( $sheet['rows'] as $row ) {
				$get = static function ( $ck ) use ( $row, $sheet ) {
					switch ( (string) $ck ) {
						case '__s1':
							return isset( $row['section'][0] ) ? $row['section'][0] : '';
						case '__s2':
							return isset( $row['section'][1] ) ? $row['section'][1] : '';
						case '__s3':
							return isset( $row['section'][2] ) ? $row['section'][2] : '';
						case '__sheet':
							return $sheet['name'];
					}
					return isset( $row['cells'][ (int) $ck ] ) ? $row['cells'][ (int) $ck ] : '';
				};

				$model = $get( $model_col );
				if ( SBPI_Util::is_empty( $model ) ) {
					continue;
				}

				$tokens = array(
					'model' => $model,
					'sheet' => $sheet['name'],
					's1'    => $get( '__s1' ),
					's2'    => $get( '__s2' ),
					's3'    => $get( '__s3' ),
				);
				$title  = SBPI_Util::render( $conf['title_tpl'] ? $conf['title_tpl'] : '{model}', $tokens );
				$key    = self::slug( $title );
				if ( '' === $key ) {
					continue;
				}

				if ( ! isset( $groups[ $key ] ) ) {
					$brand = null;
					if ( '' !== trim( $conf['brand'] ) ) {
						$brand = array( trim( $conf['brand'] ), self::slug( $conf['brand'] ), trim( $conf['brand'] ) );
					}
					$groups[ $key ] = array(
						'key'       => $key,
						'sheet'     => $sheet['name'],
						'model'     => $model,
						'title'     => $title,
						'slug'      => $key,
						'brand'     => $brand,
						'category'  => SBPI_Util::render( $conf['category'], $tokens ),
						'tags'      => array(),
						'attr_defs' => array(),
						'rows'      => array(),
						'sku'       => '',
						'image'     => '',
						'desc'      => '',
						'short'     => '',
						'lines'     => array(),
						'detect'    => $model . ' ' . $sheet['name'] . ' ' . $tokens['s1'],
					);
				}
				$g = &$groups[ $key ];
				$g['lines'][] = $sheet['name'] . ':' . $row['line'];

				$entry = array(
					'attrs' => array(),
					'price' => '',
					'sale'  => '',
					'stock' => '',
					'sku'   => '',
					'image' => '',
				);
				foreach ( $conf['columns'] as $ck => $col ) {
					$raw = $get( $ck );
					if ( SBPI_Util::is_empty( $raw ) ) {
						continue;
					}
					switch ( $col['role'] ) {
						case 'var_attr':
						case 'info_attr':
							$name = '' !== trim( $col['name'] ) ? trim( $col['name'] ) : SBPI_Util::header_label( (string) $ck );
							$aid  = SBPI_Util::key( $name );
							if ( ! isset( $g['attr_defs'][ $aid ] ) ) {
								$g['attr_defs'][ $aid ] = array(
									'name'      => $name,
									'slug'      => $col['slug'],
									'role'      => $col['role'],
									'values'    => array(),
								);
							}
							$values = ! empty( $col['split'] ) ? SBPI_Util::split( $raw, $sep ) : array( $raw );
							foreach ( $values as $v ) {
								if ( ! in_array( $v, $g['attr_defs'][ $aid ]['values'], true ) ) {
									$g['attr_defs'][ $aid ]['values'][] = $v;
								}
							}
							$entry['attrs'][ $aid ] = $values;
							break;
						case 'price':
							$entry['price'] = SBPI_Util::to_number( $raw );
							break;
						case 'sale_price':
							$entry['sale'] = SBPI_Util::to_number( $raw );
							break;
						case 'stock':
							$entry['stock'] = SBPI_Util::to_number( $raw );
							break;
						case 'sku':
							$entry['sku'] = preg_replace( '/\s+/', '', SBPI_Util::latin_digits( $raw ) );
							break;
						case 'image':
							$entry['image'] = $raw;
							if ( '' === $g['image'] ) {
								$g['image'] = $raw;
							}
							break;
						case 'description':
							$g['desc'] = '' === $g['desc'] ? $raw : $g['desc'];
							break;
						case 'short_desc':
							$g['short'] = '' === $g['short'] ? $raw : $g['short'];
							break;
						case 'category':
							$g['category'] = $raw;
							break;
						case 'tags':
							$g['tags'] = array_values( array_unique( array_merge( $g['tags'], SBPI_Util::split( $raw, $sep ) ) ) );
							break;
						case 'brand':
							if ( ! $g['brand'] ) {
								$g['brand'] = array( $raw, self::slug( $raw ), $raw );
							}
							break;
					}
				}
				$g['rows'][] = $entry;
				unset( $g );
			}
		}

		$products = array();
		$max_var  = max( 1, (int) $global['max_variations'] );
		foreach ( $groups as $g ) {
			$products[] = self::finalize( $g, $global, $glossary, $notes, $max_var, $warnings );
		}
		return array(
			'products' => $products,
			'warnings' => $warnings,
		);
	}

	/**
	 * Turn a group into a product spec (type, attributes, variations, SEO).
	 *
	 * @param array    $g        Group.
	 * @param array    $global   Global settings.
	 * @param array    $glossary Glossary.
	 * @param string[] $notes    Sheet notes.
	 * @param int      $max_var  Variation cap.
	 * @param string[] $warnings Warnings (by ref).
	 * @return array
	 */
	private static function finalize( array $g, array $global, array $glossary, array $notes, $max_var, array &$warnings ) {
		if ( ! $g['brand'] ) {
			$g['brand'] = self::detect_brand( $g['detect'] );
		}

		// A "variation" attribute with a single value across the group is just a spec.
		$axes = array();
		foreach ( $g['attr_defs'] as $aid => $def ) {
			if ( 'var_attr' === $def['role'] && count( $def['values'] ) > 1 ) {
				$axes[] = $aid;
			} else {
				$g['attr_defs'][ $aid ]['role'] = 'info_attr';
			}
		}

		// Info attributes whose value changes between rows describe a variation.
		$varying = array();
		foreach ( $g['attr_defs'] as $aid => $def ) {
			if ( 'info_attr' === $def['role'] && count( $def['values'] ) > 1 && $axes ) {
				$varying[] = $aid;
			}
		}

		$variations = array();
		$truncated  = false;
		if ( $axes ) {
			foreach ( $g['rows'] as $row ) {
				$lists = array();
				foreach ( $axes as $aid ) {
					$lists[ $aid ] = isset( $row['attrs'][ $aid ] ) ? $row['attrs'][ $aid ] : array( '' );
				}
				$info = array();
				foreach ( $varying as $aid ) {
					if ( isset( $row['attrs'][ $aid ] ) ) {
						$info[] = $g['attr_defs'][ $aid ]['name'] . ': ' . implode( '، ', $row['attrs'][ $aid ] );
					}
				}
				foreach ( self::cartesian( $lists ) as $combo ) {
					$ckey = md5( wp_json_encode( $combo ) );
					if ( isset( $variations[ $ckey ] ) ) {
						continue;
					}
					if ( count( $variations ) >= $max_var ) {
						$truncated = true;
						break 2;
					}
					$variations[ $ckey ] = array(
						'key'   => substr( $ckey, 0, 10 ),
						'attrs' => $combo,
						'price' => '' !== $row['price'] ? $row['price'] : (string) $global['default_price'],
						'sale'  => $row['sale'],
						'stock' => $row['stock'],
						'sku'   => '',
						'image' => $row['image'],
						'desc'  => implode( ' · ', $info ),
					);
				}
			}
			// A row-level SKU only identifies a variation when the row maps to exactly one.
			foreach ( $g['rows'] as $row ) {
				if ( '' === $row['sku'] ) {
					continue;
				}
				$matches = array();
				foreach ( $variations as $vk => $v ) {
					$ok = true;
					foreach ( $axes as $aid ) {
						$vals = isset( $row['attrs'][ $aid ] ) ? $row['attrs'][ $aid ] : array( '' );
						if ( ! in_array( $v['attrs'][ $aid ], $vals, true ) ) {
							$ok = false;
							break;
						}
					}
					if ( $ok ) {
						$matches[] = $vk;
					}
				}
				if ( 1 === count( $matches ) ) {
					$variations[ $matches[0] ]['sku'] = $row['sku'];
				}
			}
		}
		if ( ! $g['attr_defs'] ) {
			$warnings[] = sprintf( '«%s»: هیچ ویژگی‌ای ندارد (احتمالاً هنوز عرضه نشده)؛ اگر لازم نیست، آن سطر را از فایل حذف کنید.', $g['title'] );
		}
		if ( $truncated ) {
			$warnings[] = sprintf( '«%s»: تعداد تنوع‌ها از سقف %d بیشتر بود و بقیه ساخته نمی‌شوند. سقف را بالا ببرید یا یک ویژگی را «نمایشی» کنید.', $g['title'], $max_var );
		}

		$first = $g['rows'][0];
		$spec  = array(
			'key'        => $g['key'],
			'sheet'      => $g['sheet'],
			'title'      => $g['title'],
			'model'      => $g['model'],
			'slug'       => $g['slug'],
			'type'       => $axes ? 'variable' : 'simple',
			'brand'      => $g['brand'],
			'category'   => array_values( array_filter( array_map( 'trim', explode( '>', $g['category'] ) ), 'strlen' ) ),
			'tags'       => $g['tags'],
			'sku'        => '' !== $first['sku'] && ! $axes ? $first['sku'] : '',
			'price'      => '' !== $first['price'] ? $first['price'] : (string) $global['default_price'],
			'sale'       => $first['sale'],
			'stock'      => $first['stock'],
			'image'      => $g['image'],
			'attributes' => array(),
			'variations' => array_values( $variations ),
			'lines'      => $g['lines'],
			'desc'       => $g['desc'],
			'short'      => $g['short'],
		);
		foreach ( $g['attr_defs'] as $aid => $def ) {
			$spec['attributes'][ $aid ] = array(
				'name'      => $def['name'],
				'slug'      => $def['slug'],
				'values'    => $def['values'],
				'variation' => in_array( $aid, $axes, true ),
			);
		}

		$spec['conditions'] = self::conditions( $spec );
		$spec['glossary']   = self::match_glossary( $spec, $glossary, $notes );
		$spec['seo']        = SBPI_SEO::build_meta( $spec, $global );
		$spec['content']    = '' !== $spec['desc'] ? $spec['desc'] : SBPI_SEO::build_description( $spec, $global );
		$spec['excerpt']    = '' !== $spec['short'] ? $spec['short'] : SBPI_SEO::build_short( $spec );
		unset( $spec['desc'], $spec['short'] );
		return $spec;
	}

	/**
	 * Schema.org itemCondition values present in the product.
	 *
	 * @param array $spec Spec.
	 * @return string[]
	 */
	private static function conditions( array $spec ) {
		$out = array();
		foreach ( $spec['attributes'] as $attr ) {
			if ( 'condition' !== $attr['slug'] ) {
				continue;
			}
			foreach ( $attr['values'] as $v ) {
				$c = self::condition_of( $v );
				if ( $c && ! in_array( $c, $out, true ) ) {
					$out[] = $c;
				}
			}
		}
		return $out;
	}

	/**
	 * Map a Persian/English grade to schema.org OfferItemCondition.
	 *
	 * @param string $value Grade text.
	 * @return string
	 */
	public static function condition_of( $value ) {
		$k = SBPI_Util::key( $value );
		if ( false !== strpos( $k, '/' ) ) {
			// "آکبند / نو" → New; "نو / استوک" is ambiguous → nothing.
			$found = array_unique( array_map( array( __CLASS__, 'condition_of' ), explode( '/', $k ) ) );
			return 1 === count( $found ) ? reset( $found ) : '';
		}
		if ( preg_match( '/refurb|ریفربیش|بازسازی/u', $k ) ) {
			return 'RefurbishedCondition';
		}
		if ( preg_match( '/استوک|کارکرده|دست ?دوم|used|اکتیو|active|open ?box|اوپن/u', $k ) ) {
			return 'UsedCondition';
		}
		if ( preg_match( '/^(نو|new|آکبند|اکبند|sealed)/u', $k ) ) {
			return 'NewCondition';
		}
		return '';
	}

	/**
	 * Glossary entries relevant to this product (whole-word match on title + values).
	 *
	 * @param array    $spec     Spec.
	 * @param array    $glossary [[term, text]].
	 * @param string[] $notes    Notes.
	 * @return array
	 */
	private static function match_glossary( array $spec, array $glossary, array $notes ) {
		$hay = $spec['title'] . ' ' . $spec['model'];
		foreach ( $spec['attributes'] as $a ) {
			$hay .= ' ' . $a['name'] . ' ' . implode( ' ', $a['values'] );
		}
		$hay = SBPI_Util::key( $hay );
		$out = array();
		foreach ( $glossary as $g ) {
			$alts = preg_split( '/\s+یا\s+|\s*\/\s*|\s*\|\s*/u', SBPI_Util::header_label( $g[0] ) );
			foreach ( $alts as $alt ) {
				$alt = SBPI_Util::key( $alt );
				if ( mb_strlen( $alt, 'UTF-8' ) < 2 ) {
					continue;
				}
				if ( preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $alt, '/' ) . '(?![\p{L}\p{N}])/u', $hay ) ) {
					$out[] = $g;
					break;
				}
			}
			if ( count( $out ) >= 10 ) {
				break;
			}
		}
		foreach ( $notes as $n ) {
			$text = trim( preg_replace( '/^(نکته|توجه|note)\s*[:：]\s*/iu', '', $n ) );
			$first_word = SBPI_Util::key( strtok( $spec['model'], ' ' ) );
			if ( '' !== $first_word && false !== strpos( SBPI_Util::key( $text ), $first_word ) ) {
				$out[] = array( 'نکته', $text );
			}
		}
		return $out;
	}

	/**
	 * Cartesian product of attribute value lists.
	 *
	 * @param array $lists aid => values.
	 * @return array[] aid => value.
	 */
	private static function cartesian( array $lists ) {
		$result = array( array() );
		foreach ( $lists as $aid => $values ) {
			$next = array();
			foreach ( $result as $partial ) {
				foreach ( $values as $v ) {
					$partial[ $aid ] = $v;
					$next[]          = $partial;
				}
			}
			$result = $next;
		}
		return $result;
	}

	/**
	 * Latin-friendly slug (falls back to WordPress' percent-encoded slug for pure Persian).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function slug( $text ) {
		$text = trim( SBPI_Util::latin_digits( (string) $text ) );
		// Titles that start in Persian keep a Persian slug (WordPress encodes it).
		if ( '' === $text || ! preg_match( '/^[A-Za-z0-9]/', $text ) ) {
			return sanitize_title( $text );
		}
		// "Ps5 Slim Digital (بازبینی 2025)" → "ps5-slim-digital-2025".
		$latin = sanitize_title( preg_replace( '/[^\x20-\x7E]+/u', ' ', $text ) );
		// Persian words outside parentheses can be the only difference between two
		// products ("Ps4 Slim 500GB جیلبریک"), so keep them distinct with a short hash.
		$outside = preg_replace( '/\([^)]*\)/u', '', $text );
		if ( preg_match( '/[^\x00-\x7F]/u', $outside ) ) {
			$latin .= '-' . substr( md5( $outside ), 0, 4 );
		}
		return $latin;
	}
}
