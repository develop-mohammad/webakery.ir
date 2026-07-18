<?php
defined( 'ABSPATH' ) || exit;

$roles   = wp_roles()->roles;
$catalog = AL_Access::menu_catalog();
$rules   = AL_Access::rules();
?>
<div class="al-card">
	<?php if ( ! AL_Plugin::licensed() ) : ?>
		<div class="al-notice warn">برای اعمال محدودیت‌ها لایسنس را از تب «لایسنس» فعال کنید. در دوره آزمایشی هم کار می‌کند.</div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'al_save_rules' ); ?>
		<input type="hidden" name="al_save_rules" value="1" />

		<div class="al-toolbar">
			<label>نقش:
				<select id="al-role-select">
					<?php foreach ( $roles as $role_key => $role ) : ?>
						<option value="<?php echo esc_attr( $role_key ); ?>"><?php echo esc_html( translate_user_role( $role['name'] ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button type="button" class="button" id="al-check-all">انتخاب همه</button>
			<button type="button" class="button" id="al-uncheck-all">حذف همه</button>
		</div>

		<?php
		$first_role = '';
		foreach ( $roles as $role_key => $role ) {
			if ( 'administrator' !== $role_key ) {
				$first_role = $role_key;
				break;
			}
		}
		foreach ( $roles as $role_key => $role ) :
			$visible = ( $role_key === $first_role );
			?>
			<div class="al-role-panel" data-role="<?php echo esc_attr( $role_key ); ?>"<?php echo $visible ? '' : ' style="display:none"'; ?>>
				<h2><?php echo esc_html( translate_user_role( $role['name'] ) ); ?></h2>
				<p class="al-muted">تیک بزنید = این بخش برای این نقش <strong>مخفی</strong> می‌شود.</p>
				<div class="al-grid">
					<?php foreach ( $catalog as $key => $item ) :
						$denied = $rules[ $role_key ] ?? array();
						$checked = in_array( $key, $denied, true ) || in_array( $item['slug'], $denied, true );
						?>
						<label class="al-item">
							<input type="checkbox" name="denied[<?php echo esc_attr( $role_key ); ?>][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $checked ); ?> />
							<span><?php echo esc_html( $item['title'] ); ?></span>
							<code dir="ltr"><?php echo esc_html( $key ); ?></code>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<p><button type="submit" class="button button-primary">ذخیره دسترسی‌ها</button></p>
	</form>
</div>
