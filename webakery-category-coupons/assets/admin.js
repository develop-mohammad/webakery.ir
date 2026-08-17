/* کد تخفیف دسته‌بندی — webakery.ir | اسکریپت پیشخوان */
( function () {
	'use strict';

	function copy( text, button ) {
		var done = function () {
			var old = button.textContent;
			button.textContent = 'کپی شد ✓';
			setTimeout( function () {
				button.textContent = old;
			}, 1500 );
		};

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( done, function () {
				window.prompt( 'کد تخفیف:', text );
			} );
			return;
		}
		window.prompt( 'کد تخفیف:', text );
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;

		if ( target.classList.contains( 'wbcc-copy-btn' ) ) {
			event.preventDefault();
			copy( target.getAttribute( 'data-code' ) || '', target );
			return;
		}

		if ( target.classList.contains( 'wbcc-cat-all' ) || target.classList.contains( 'wbcc-cat-none' ) ) {
			event.preventDefault();
			var check = target.classList.contains( 'wbcc-cat-all' );
			var list = target.closest( '.wbcc-card-box' ).querySelector( '.wbcc-cat-list[data-role="include"]' );
			if ( ! list ) {
				return;
			}
			list.querySelectorAll( '.wbcc-cat' ).forEach( function ( row ) {
				if ( row.hasAttribute( 'hidden' ) ) {
					return; // فقط موارد نمایش‌داده‌شده (نتیجه جست‌وجو)
				}
				var input = row.querySelector( 'input[type="checkbox"]' );
				if ( input ) {
					input.checked = check;
				}
			} );
		}
	} );

	document.addEventListener( 'input', function ( event ) {
		if ( ! event.target.classList.contains( 'wbcc-cat-search' ) ) {
			return;
		}
		var term = event.target.value.trim().toLowerCase();
		var box = event.target.closest( '.wbcc-card-box' );
		box.querySelectorAll( '.wbcc-cat-list .wbcc-cat' ).forEach( function ( row ) {
			var label = ( row.textContent || '' ).toLowerCase();
			if ( ! term || label.indexOf( term ) !== -1 ) {
				row.removeAttribute( 'hidden' );
			} else {
				row.setAttribute( 'hidden', 'hidden' );
			}
		} );
	} );
} )();
