import { render, useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { SlotFillProvider, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import NewslettersList from './views/newsletters-list';
import NewsletterDetail from './views/newsletter-detail';
import CreateNewsletter from './views/create-newsletter';

import useKeyboardShortcuts from './hooks/use-keyboard-shortcuts';
import KeyboardShortcutsHelpModal from './components/keyboard-shortcuts-help-modal';

import './style.css';

function App() {
	const [ view, setView ] = useState( 'newsletters-list' );
	const [ currentNewsletterId, setCurrentNewsletterId ] = useState( null );
	const [ notification, setNotification ] = useState( null );
	const [ showHelpModal, setShowHelpModal ] = useState( false );

	// Auto-navigate to newsletter-detail if newsletter_id is in URL (return from editor).
	useEffect( () => {
		const params = new URLSearchParams( window.location.search );
		const nlId = params.get( 'newsletter_id' );
		if ( nlId ) {
			setView( 'newsletter-detail' );
			setCurrentNewsletterId( parseInt( nlId, 10 ) );
			// Clean up URL so refreshing doesn't re-trigger.
			const url = new URL( window.location );
			url.searchParams.delete( 'newsletter_id' );
			window.history.replaceState( {}, '', url );
		}
	}, [] );

	const navigate = useCallback( ( newView, id = null ) => {
		setView( newView );
		if ( id !== null ) {
			setCurrentNewsletterId( id );
		}
	}, [] );

	const notify = useCallback( ( message, type = 'success' ) => {
		setNotification( { message, type } );
		setTimeout( () => setNotification( null ), 5000 );
	}, [] );

	// Global shortcut: Alt+Shift+H opens the keyboard shortcuts help modal.
	const globalShortcuts = useMemo( () => [
		{ key: 'h', handler: () => setShowHelpModal( ( v ) => ! v ) },
	], [] );
	useKeyboardShortcuts( globalShortcuts );

	return (
		<SlotFillProvider>
			<div className="swl-app">
				{ notification && (
					<div
						className={ `swl-notification swl-notification--${ notification.type }` }
						role="status"
						aria-live="polite"
					>
						{ notification.message }
					</div>
				) }

				<div className="swl-global-help-row">
					<Button
						variant="tertiary"
						onClick={ () => setShowHelpModal( true ) }
						aria-keyshortcuts="Alt+Shift+H"
					>
						{ __( 'Keyboard Shortcuts', 'swiftletter' ) }
						<span className="swl-shortcut-badge" aria-hidden="true">Alt+Shift+H</span>
					</Button>
				</div>

				{ showHelpModal && (
					<KeyboardShortcutsHelpModal onClose={ () => setShowHelpModal( false ) } />
				) }

				{ view === 'newsletters-list' && (
					<NewslettersList navigate={ navigate } notify={ notify } />
				) }

				{ view === 'create-newsletter' && (
					<CreateNewsletter navigate={ navigate } notify={ notify } />
				) }

				{ view === 'newsletter-detail' && currentNewsletterId && (
					<NewsletterDetail
						newsletterId={ currentNewsletterId }
						navigate={ navigate }
						notify={ notify }
					/>
				) }
			</div>
		</SlotFillProvider>
	);
}

document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'swiftletter-dashboard' );
	if ( root ) {
		render( <App />, root );
	}
} );
