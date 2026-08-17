/* کد تخفیف دسته‌بندی — webakery.ir | دریافت کد توسط مشتری */
( function () {
	'use strict';

	function show( card, message, isError ) {
		var box = card.querySelector( '.wbcc-message' );
		if ( ! box ) {
			return;
		}
		box.textContent = message;
		box.classList.toggle( 'is-error', !! isError );
		box.classList.toggle( 'is-ok', ! isError );
		box.hidden = ! message;
	}

	function claim( card ) {
		var button = card.querySelector( '.wbcc-btn' );
		var email = card.querySelector( '.wbcc-email' );
		var body = new URLSearchParams();

		body.append( 'action', 'wbcc_claim' );
		body.append( 'nonce', window.WBCC.nonce );
		body.append( 'campaign', card.getAttribute( 'data-campaign' ) || '0' );
		if ( email ) {
			body.append( 'email', email.value );
		}

		button.disabled = true;
		var label = button.textContent;
		button.textContent = 'در حال ساخت کد…';
		show( card, '', false );

		fetch( window.WBCC.ajax, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				button.disabled = false;
				button.textContent = label;

				if ( ! json || ! json.success ) {
					show( card, ( json && json.data && json.data.message ) || 'خطا در ساخت کد تخفیف.', true );
					return;
				}

				var result = card.querySelector( '.wbcc-result' );
				card.querySelector( '.wbcc-code' ).textContent = json.data.code;
				card.querySelector( '.wbcc-expiry' ).textContent = json.data.expiry || '';
				result.hidden = false;
				show( card, json.data.message || '', false );
			} )
			.catch( function () {
				button.disabled = false;
				button.textContent = label;
				show( card, 'ارتباط با سرور برقرار نشد. دوباره تلاش کنید.', true );
			} );
	}

	document.addEventListener( 'click', function ( event ) {
		var card = event.target.closest ? event.target.closest( '.wbcc-card' ) : null;
		if ( ! card ) {
			return;
		}

		if ( event.target.classList.contains( 'wbcc-btn' ) ) {
			event.preventDefault();
			claim( card );
			return;
		}

		if ( event.target.classList.contains( 'wbcc-copy' ) ) {
			event.preventDefault();
			var code = card.querySelector( '.wbcc-code' ).textContent;
			var button = event.target;
			var done = function () {
				button.textContent = 'کپی شد ✓';
				setTimeout( function () {
					button.textContent = 'کپی';
				}, 1500 );
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( code ).then( done, function () {
					window.prompt( 'کد تخفیف:', code );
				} );
			} else {
				window.prompt( 'کد تخفیف:', code );
			}
		}
	} );
} )();
