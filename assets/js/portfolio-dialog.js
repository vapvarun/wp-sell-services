/**
 * Vendor portfolio: open a project in its native <dialog>, close it from its button or the backdrop.
 *
 * Enqueued by templates/partials/vendor-portfolio.php; one delegated listener, however many
 * portfolios the page shows.
 *
 * @package WPSellServices
 */
document.addEventListener( 'click', function ( e ) {
	var open = e.target.closest( '[data-wpss-portfolio-open]' );
	var dlg;
	if ( open ) {
		dlg = document.getElementById( open.getAttribute( 'data-wpss-portfolio-open' ) );
		if ( dlg && dlg.showModal && ! dlg.open ) {
			dlg.showModal();
		}
		return;
	}
	if ( e.target.closest( '[data-wpss-portfolio-close]' ) ) {
		e.target.closest( 'dialog' ).close();
	} else if ( e.target.matches && e.target.matches( 'dialog.wpss-portfolio-public__dialog' ) ) {
		e.target.close(); // Click on the backdrop.
	}
} );
