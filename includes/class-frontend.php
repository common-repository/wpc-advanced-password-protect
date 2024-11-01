<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Wpcpp_Frontend' ) ) {
	class Wpcpp_Frontend {
		protected static $instance = null;

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function __construct() {
			add_action( 'wp', [ $this, 'do_login' ] );
			add_action( 'template_redirect', [ $this, 'get_template' ], 1 );
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

			// Purchasable
			if ( Wpcpp_Backend()::get_setting( 'unpurchasable', 'yes' ) === 'yes' ) {
				add_filter( 'woocommerce_is_purchasable', [ $this, 'is_purchasable' ], 9999, 2 );
			}
		}

		public function do_login() {
			global $wp_did_header;

			if ( ! $wp_did_header || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
				return;
			}

			if ( ! ( $rule = isset( $_POST['wpcpp_login'] ) ? sanitize_text_field( $_POST['wpcpp_login'] ) : false ) ) {
				return;
			}

			if ( ! ( $password = isset( $_POST['post_password'] ) ? sanitize_text_field( $_POST['post_password'] ) : false ) ) {
				return;
			}

			$rules = Wpcpp_Backend()::get_rules();

			if ( empty( $rules[ $rule ]['password'] ) ) {
				return;
			}

			if ( $last_attempt = self::get_session( 'wpcpp_' . $rule . '_t' ) ) {
				if ( time() > $last_attempt ) {
					// remove attempt counter
					self::delete_session( 'wpcpp_' . $rule . '_a' ); // attempt count
					self::delete_session( 'wpcpp_' . $rule . '_t' ); // attempt time
				}
			}

			if ( in_array( $password, $rules[ $rule ]['password'] ) ) {
				// correct password
				self::set_cookie_password( $rule, $password );
				self::delete_cookie( 'wpcpp_' . $rule . '_a' );
				self::delete_session( 'wpcpp_' . $rule . '_a' );
			} else {
				// incorrect password
				self::set_cookie( 'wpcpp_' . $rule . '_i', time() ); // incorrect time

				$attempt = isset( $_COOKIE[ 'wpcpp_' . $rule . '_a' ] ) ? absint( $_COOKIE[ 'wpcpp_' . $rule . '_a' ] ) : 0;
				$attempt = ( $session_attempt = self::get_session( 'wpcpp_' . $rule . '_a' ) ) ? absint( $session_attempt ) : $attempt;
				$attempt ++;
				self::set_session( 'wpcpp_' . $rule . '_a', $attempt );
				self::set_session( 'wpcpp_' . $rule . '_t', time() + 1800 );
				self::set_cookie( 'wpcpp_' . $rule . '_a', $attempt, time() + 1800 );
			}

			// reload page
			wp_safe_redirect( add_query_arg( null, null ) );
		}

		function get_template() {
			global $wp_did_header;

			if ( ! $wp_did_header ) {
				return;
			}

			$object = get_queried_object();

			if ( $rule = self::get_protected_rule( $object ) ) {
				if ( self::is_unprotected( $rule ) ) {
					return;
				}

				$this->prevent_indexing();
				$this->prevent_caching();
				$this->password_protected( $rule );
			}
		}

		private function password_protected( $rule = '' ) {
			global $wp, $wp_query;

			$post = $this->get_password_post( $rule );

			// Override main query
			$wp_query->post                 = $post;
			$wp_query->posts                = [ $post ];
			$wp_query->queried_object       = $post;
			$wp_query->queried_object_id    = $post->ID;
			$wp_query->found_posts          = 1;
			$wp_query->post_count           = 1;
			$wp_query->max_num_pages        = 1;
			$wp_query->comment_count        = 0;
			$wp_query->comments             = [];
			$wp_query->is_singular          = true;
			$wp_query->is_page              = true;
			$wp_query->is_single            = false;
			$wp_query->is_attachment        = false;
			$wp_query->is_archive           = false;
			$wp_query->is_category          = false;
			$wp_query->is_tag               = false;
			$wp_query->is_tax               = false;
			$wp_query->is_author            = false;
			$wp_query->is_date              = false;
			$wp_query->is_year              = false;
			$wp_query->is_month             = false;
			$wp_query->is_day               = false;
			$wp_query->is_time              = false;
			$wp_query->is_search            = false;
			$wp_query->is_feed              = false;
			$wp_query->is_comment_feed      = false;
			$wp_query->is_trackback         = false;
			$wp_query->is_home              = false;
			$wp_query->is_embed             = false;
			$wp_query->is_404               = false;
			$wp_query->is_paged             = false;
			$wp_query->is_admin             = false;
			$wp_query->is_preview           = false;
			$wp_query->is_robots            = false;
			$wp_query->is_posts_page        = false;
			$wp_query->is_post_type_archive = false;

			// Update globals
			$GLOBALS['wp_query'] = $wp_query;

			$wp->register_globals();
		}

		private function get_password_post( $rule = '' ) {
			$post_id              = current_time( 'U' );
			$post                 = new stdClass();
			$post->ID             = $post_id;
			$post->post_author    = 1;
			$post->post_date      = current_time( 'mysql' );
			$post->post_date_gmt  = current_time( 'mysql', 1 );
			$post->post_status    = 'publish';
			$post->comment_status = 'closed';
			$post->comment_count  = 0;
			$post->ping_status    = 'closed';
			$post->post_type      = 'page';
			$post->filter         = 'raw';
			$post->post_name      = 'wpcpp-post-' . $post_id;
			$post->post_title     = self::get_login_title( $rule );
			$post->post_content   = self::get_login_form( $rule );

			// Convert to WP_Post object
			$wp_post = new WP_Post( $post );

			return apply_filters( 'wpcpp_template_password_post', $wp_post );
		}

		public function get_login_title( $rule = '' ) {
			$title = Wpcpp_Backend()::get_setting( 'title', esc_html__( 'Login Required', 'wpc-advanced-password-protect' ) );
			$rules = Wpcpp_Backend()::get_rules();

			if ( ! empty( $rules[ $rule ]['title'] ) ) {
				$title = esc_html( $rules[ $rule ]['title'] );
			}

			if ( self::is_product_archive() ) {
				$title = str_replace( '{title}', single_term_title( '', false ), $title );
			} elseif ( self::is_product_single() ) {
				$title = str_replace( '{title}', get_the_title(), $title );
			}

			return apply_filters( 'wpcpp_get_login_title', $title, $rule );
		}

		public function get_login_message( $rule = '' ) {
			$message = Wpcpp_Backend()::get_setting( 'message', esc_html__( 'This content is password protected. To view it please enter your password below:', 'wpc-advanced-password-protect' ) );
			$rules   = Wpcpp_Backend()::get_rules();

			if ( ! empty( $rules[ $rule ]['message'] ) ) {
				$message = wp_kses_post( $rules[ $rule ]['message'] );
			}

			if ( self::is_product_archive() ) {
				$message = str_replace( '{title}', single_term_title( '', false ), $message );
			} elseif ( self::is_product_single() ) {
				$message = str_replace( '{title}', get_the_title(), $message );
			}

			$message = apply_filters( 'the_content', $message );

			return apply_filters( 'wpcpp_get_login_message', $message, $rule );
		}

		public function get_login_form( $rule = '' ) {
			$container_class = apply_filters( 'wpcpp_login_form_container_class', 'wpcpp-login-form-container' );
			$form_class      = apply_filters( 'wpcpp_login_form_class', 'wpcpp-login-form post-password-form' );
			$form_id         = 'wpcpp-login-form';
			$form_action     = add_query_arg( null, null );
			$label           = Wpcpp_Backend()::get_setting( 'label', esc_html__( 'Password:', 'wpc-advanced-password-protect' ) );
			$placeholder     = Wpcpp_Backend()::get_setting( 'placeholder', '' );
			$rules           = Wpcpp_Backend()::get_rules();
			ob_start();
			?>
            <div class="<?php echo esc_attr( $container_class ); ?>">
                <div class="wpcpp-login-form-message"><?php echo wp_kses_post( self::get_login_message( $rule ) ); ?></div>
				<?php if ( ! empty( $rules[ $rule ]['password'] ) && is_array( $rules[ $rule ]['password'] ) ) {
					$is_attempt_limit = false;

					if ( ( $attempt_limit = absint( Wpcpp_Backend()::get_setting( 'attempt', 5 ) ) ) > 0 ) {
						$attempt = isset( $_COOKIE[ 'wpcpp_' . $rule . '_a' ] ) ? absint( $_COOKIE[ 'wpcpp_' . $rule . '_a' ] ) : 0;
						$attempt = ( $session_attempt = self::get_session( 'wpcpp_' . $rule . '_a' ) ) ? absint( $session_attempt ) : $attempt;

						if ( $attempt >= $attempt_limit ) {
							$is_attempt_limit = true;
							echo '<p class="wpcpp-login-form-error-message wpcpp-login-form-attempt-password-message">' . esc_html( Wpcpp_Backend()::get_setting( 'attempt_message', esc_html__( 'You have reached the attempt limit! Please try again after 30 minutes.', 'wpc-advanced-password-protect' ) ) ) . '</p>';
						}
					}

					if ( ! $is_attempt_limit ) {
						?>
                        <form id="<?php echo esc_attr( $form_id ); ?>" action="<?php echo esc_url( $form_action ); ?>" class="<?php echo esc_attr( $form_class ); ?>" method="post">
							<?php if ( isset( $_COOKIE[ 'wpcpp_' . $rule . '_i' ] ) ) {
								echo '<p class="wpcpp-login-form-error-message wpcpp-login-form-incorrect-password-message">' . esc_html( Wpcpp_Backend()::get_setting( 'incorrect_message', esc_html__( 'Incorrect password! Please try again.', 'wpc-advanced-password-protect' ) ) ) . '</p>';
								self::delete_cookie( 'wpcpp_' . $rule . '_i' );
							} ?>
                            <p>
								<?php printf(
									'<label class="wpcpp-login-form-label" for="%1$s">%2$s <span class="wpcpp-login-form-password-wrapper"><input type="password" name="post_password" id="%1$s" class="wpcpp-login-form-password" size="25" placeholder="%3$s" autofocus/><span class="wpcpp-login-form-password-visible"></span></span></label>',
									esc_attr( 'wpcpp-password-input-' . $form_id ),
									( $label ? sprintf( '<span class="wpcpp-login-form-label-text">%s</span>', esc_html( $label ) ) : '' ),
									esc_attr( $placeholder )
								); ?>
                                <input type="submit" name="submit" class="wpcpp-login-form-submit" value="<?php echo esc_attr( Wpcpp_Backend()::get_setting( 'button', esc_html__( 'Login', 'wpc-advanced-password-protect' ) ) ); ?>"/><input type="hidden" name="wpcpp_login" value="<?php echo esc_attr( $rule ); ?>"/>
                            </p>
                        </form>
						<?php
					}
				} ?>
            </div>
			<?php
			return apply_filters( 'wpcpp_get_login_form', ob_get_clean(), $rule );
		}

		function enqueue_scripts() {
			wp_enqueue_style( 'wpcpp-frontend', WPCPP_URI . 'assets/css/frontend.css', [], WPCPP_VERSION );
			wp_enqueue_script( 'wpcpp-frontend', WPCPP_URI . 'assets/js/frontend.js', [ 'jquery' ], WPCPP_VERSION, true );
		}

		private function prevent_caching() {
			// Set headers to prevent caching
			nocache_headers();

			// Set constants to prevent caching in certain caching plugins
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
				define( 'DONOTCACHEOBJECT', true );
			}
			if ( ! defined( 'DONOTCACHEDB' ) ) {
				define( 'DONOTCACHEDB', true );
			}

			do_action( 'wpcpp_prevent_caching' );
		}

		private function prevent_indexing() {
			// noindex this page - we add X-Robots-Tag header and set meta robots
			if ( ! headers_sent() ) {
				header( 'X-Robots-Tag: noindex, nofollow' );
			}

			add_action( 'wp_head', [ $this, 'meta_robots_noindex' ], 5 );

			do_action( 'wpcpp_prevent_indexing' );
		}

		public function meta_robots_noindex() {
			echo '<meta name="robots" content="noindex, nofollow" />' . "\n";
		}

		public static function is_product_archive() {
			return is_product_category() || is_product_tag() || is_product_taxonomy();
		}

		public static function is_product_single() {
			return is_product();
		}

		public static function get_protected_rule( $object ) {
			$key = false;

			if ( is_a( $object, 'WP_Term' ) ) {
				// product archive
				$rules = Wpcpp_Backend()::get_rules();

				if ( is_array( $rules ) && ! empty( $rules ) ) {
					foreach ( $rules as $k => $rule ) {
						if ( self::check_apply_term( $object, $rule ) && self::check_timer( $rule['timer'] ) ) {
							$key = $k;
							break;
						}
					}
				}
			} elseif ( ( is_a( $object, 'WP_Post' ) && ( 'product' === $object->post_type ) ) || is_a( $object, 'WC_Product' ) ) {
				// product single
				$rules = Wpcpp_Backend()::get_rules();

				if ( is_array( $rules ) && ! empty( $rules ) ) {
					foreach ( $rules as $k => $rule ) {
						if ( self::check_apply_product( $object, $rule ) && self::check_timer( $rule['timer'] ) ) {
							$key = $k;
							break;
						}
					}
				}
			}

			return $key;
		}

		public static function check_apply_term( $term, $rule ) {
			$apply     = $rule['apply'];
			$apply_val = $rule['apply_val'];
			$taxonomy  = $term->taxonomy;
			$slug      = $term->slug;

			if ( ! empty( $apply_val['terms'] ) ) {
				if ( ( $apply === $taxonomy ) && in_array( $slug, $apply_val['terms'] ) ) {
					return true;
				}
			}

			return false;
		}

		public static function check_apply_product( $product, $rule ) {
			$apply      = $rule['apply'];
			$apply_val  = $rule['apply_val'];
			$product_id = 0;

			if ( is_numeric( $product ) ) {
				$product_id = $product;
			} elseif ( is_a( $product, 'WC_Product' ) ) {
				$product_id = $product->get_id();
			} elseif ( is_a( $product, 'WP_Post' ) ) {
				$product_id = $product->ID;
			}

			if ( ! $product_id ) {
				return false;
			}

			switch ( $apply ) {
				case 'apply_all':
					return true;
				case 'apply_product':
					if ( ! empty( $apply_val['products'] ) ) {
						if ( in_array( $product_id, $apply_val['products'] ) ) {
							return true;
						}
					}

					return false;
				default:
					if ( ! empty( $apply_val['terms'] ) ) {
						$taxonomy = $apply;

						if ( has_term( $apply_val['terms'], $taxonomy, $product_id ) ) {
							return true;
						}
					}

					return false;
			}
		}

		public static function check_timer( $timer ) {
			$check = true;

			if ( ! empty( $timer ) ) {
				foreach ( $timer as $time ) {
					$check_item = false;
					$time_type  = isset( $time['type'] ) ? trim( $time['type'] ) : '';
					$time_value = isset( $time['val'] ) ? trim( $time['val'] ) : '';

					switch ( $time_type ) {
						case 'date_range':
							$date_range = array_map( 'trim', explode( '-', $time_value ) );

							if ( count( $date_range ) === 2 ) {
								$date_range_start = trim( $date_range[0] );
								$date_range_end   = trim( $date_range[1] );
								$current_date     = strtotime( current_time( 'm/d/Y' ) );

								if ( $current_date >= strtotime( $date_range_start ) && $current_date <= strtotime( $date_range_end ) ) {
									$check_item = true;
								}
							} elseif ( count( $date_range ) === 1 ) {
								$date_range_start = trim( $date_range[0] );

								if ( strtotime( current_time( 'm/d/Y' ) ) === strtotime( $date_range_start ) ) {
									$check_item = true;
								}
							}

							break;
						case 'date_multi':
							$multiple_dates_arr = array_map( 'trim', explode( ', ', $time_value ) );

							if ( in_array( current_time( 'm/d/Y' ), $multiple_dates_arr ) ) {
								$check_item = true;
							}

							break;
						case 'date_even':
							if ( (int) current_time( 'd' ) % 2 === 0 ) {
								$check_item = true;
							}

							break;
						case 'date_odd':
							if ( (int) current_time( 'd' ) % 2 !== 0 ) {
								$check_item = true;
							}

							break;
						case 'date_on':
							if ( strtotime( current_time( 'm/d/Y' ) ) === strtotime( $time_value ) ) {
								$check_item = true;
							}

							break;
						case 'date_before':
							if ( strtotime( current_time( 'm/d/Y' ) ) < strtotime( $time_value ) ) {
								$check_item = true;
							}

							break;
						case 'date_after':
							if ( strtotime( current_time( 'm/d/Y' ) ) > strtotime( $time_value ) ) {
								$check_item = true;
							}

							break;
						case 'date_time_before':
							$current_time = current_time( 'm/d/Y h:i a' );

							if ( strtotime( $current_time ) < strtotime( $time_value ) ) {
								$check_item = true;
							}

							break;
						case 'date_time_after':
							$current_time = current_time( 'm/d/Y h:i a' );

							if ( strtotime( $current_time ) > strtotime( $time_value ) ) {
								$check_item = true;
							}

							break;
						case 'time_range':
							$time_range = array_map( 'trim', explode( '-', $time_value ) );

							if ( count( $time_range ) === 2 ) {
								$current_time     = strtotime( current_time( 'm/d/Y h:i a' ) );
								$current_date     = current_time( 'm/d/Y' );
								$time_range_start = $current_date . ' ' . $time_range[0];
								$time_range_end   = $current_date . ' ' . $time_range[1];

								if ( $current_time >= strtotime( $time_range_start ) && $current_time <= strtotime( $time_range_end ) ) {
									$check_item = true;
								}
							}

							break;
						case 'time_before':
							$current_time = current_time( 'm/d/Y h:i a' );
							$current_date = current_time( 'm/d/Y' );

							if ( strtotime( $current_time ) < strtotime( $current_date . ' ' . $time_value ) ) {
								$check_item = true;
							}

							break;
						case 'time_after':
							$current_time = current_time( 'm/d/Y h:i a' );
							$current_date = current_time( 'm/d/Y' );

							if ( strtotime( $current_time ) > strtotime( $current_date . ' ' . $time_value ) ) {
								$check_item = true;
							}

							break;
						case 'weekly_every':
							if ( strtolower( current_time( 'D' ) ) === $time_value ) {
								$check_item = true;
							}

							break;
						case 'week_even':
							if ( (int) current_time( 'W' ) % 2 === 0 ) {
								$check_item = true;
							}

							break;
						case 'week_odd':
							if ( (int) current_time( 'W' ) % 2 !== 0 ) {
								$check_item = true;
							}

							break;
						case 'week_no':
							if ( (int) current_time( 'W' ) === (int) $time_value ) {
								$check_item = true;
							}

							break;
						case 'monthly_every':
							if ( strtolower( current_time( 'j' ) ) === $time_value ) {
								$check_item = true;
							}

							break;
						case 'month_no':
							if ( (int) current_time( 'm' ) === (int) $time_value ) {
								$check_item = true;
							}

							break;
						case 'every_day':
							$check_item = true;

							break;
					}

					$check &= $check_item;
				}
			}

			return $check;
		}

		public static function set_cookie( $name, $value, $expire = 0 ) {
			$secure   = apply_filters( 'wpcpp_cookie_secure', wc_site_is_https() && is_ssl() );
			$httponly = apply_filters( 'wpcpp_cookie_httponly', true );

			wc_setcookie( $name, $value, $expire, $secure, $httponly );
		}

		public static function delete_cookie( $name ) {
			unset( $_COOKIE[ $name ] );
			$secure   = apply_filters( 'wpcpp_cookie_secure', wc_site_is_https() && is_ssl() );
			$httponly = apply_filters( 'wpcpp_cookie_httponly', true );

			wc_setcookie( $name, '', time() - 3600, $secure, $httponly );
		}

		public static function set_session( $name, $value ) {
			if ( isset( WC()->session ) ) {
				WC()->session->set( $name, $value );
			}
		}

		public static function delete_session( $name ) {
			if ( isset( WC()->session ) ) {
				WC()->session->set( $name, null );
			}
		}

		public static function get_session( $name ) {
			if ( isset( WC()->session ) ) {
				return WC()->session->get( $name );
			}

			return null;
		}

		public static function set_cookie_password( $rule, $password ) {
			require_once ABSPATH . WPINC . '/class-phpass.php';

			$hash         = new PasswordHash( 8, true );
			$value        = $hash->HashPassword( wp_unslash( $password ) );
			$expiry_dates = absint( Wpcpp_Backend()::get_setting( 'expiry', 7 ) );

			if ( $expiry_dates > 0 ) {
				$expiry = time() + $expiry_dates * 86400;
			} else {
				$expiry = 0;
			}

			self::set_cookie( 'wpcpp_' . $rule, $value, $expiry );
		}

		public static function check_password( $rule ) {
			require_once ABSPATH . WPINC . '/class-phpass.php';

			$hash        = new PasswordHash( 8, true );
			$cookie_name = 'wpcpp_' . $rule;

			if ( empty( $_COOKIE[ $cookie_name ] ) ) {
				return false;
			}

			$password = sanitize_text_field( $_COOKIE[ $cookie_name ] );
			$rules    = Wpcpp_Backend()::get_rules();

			if ( ! empty( $rules[ $rule ]['password'] ) && is_array( $rules[ $rule ]['password'] ) ) {
				foreach ( $rules[ $rule ]['password'] as $pw ) {
					if ( $hash->CheckPassword( $pw, wp_unslash( $password ) ) ) {
						return true;
					}
				}
			}

			return false;
		}

		public static function check_roles( $rule ) {
			return false;
		}

		public static function check_users( $rule ) {
			return false;
		}

		public static function is_unprotected( $rule ) {
			return apply_filters( 'wpcpp_is_unprotected', self::check_users( $rule ) || self::check_roles( $rule ) || self::check_password( $rule ), $rule );
		}

		public static function is_purchasable( $purchasable, $product ) {
			if ( $rule = self::get_protected_rule( $product ) ) {
				if ( ! self::is_unprotected( $rule ) ) {
					$purchasable = false;
				}
			}

			return apply_filters( 'wpcpp_is_purchasable', $purchasable, $product );
		}
	}

	function Wpcpp_Frontend() {
		return Wpcpp_Frontend::instance();
	}

	Wpcpp_Frontend();
}
