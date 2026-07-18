<?php
defined( 'ABSPATH' ) || exit;

$users = get_users(
	array(
		'number'  => 200,
		'orderby' => 'registered',
		'order'   => 'DESC',
	)
);
?>
<div class="al-card">
	<h2>کاربران</h2>
	<table class="al-table">
		<thead>
			<tr>
				<th>کاربر</th>
				<th>ایمیل</th>
				<th>نقش</th>
				<th>ثبت‌نام</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $users as $user ) : ?>
			<tr>
				<td><strong><?php echo esc_html( $user->display_name ); ?></strong><br><span class="al-muted"><?php echo esc_html( $user->user_login ); ?></span></td>
				<td><?php echo esc_html( $user->user_email ); ?></td>
				<td><?php echo esc_html( implode( ', ', array_map( 'translate_user_role', $user->roles ) ) ); ?></td>
				<td><?php echo esc_html( mysql2date( 'Y/m/d', $user->user_registered ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
