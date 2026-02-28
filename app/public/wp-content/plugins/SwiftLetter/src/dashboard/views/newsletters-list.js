import { useState, useEffect, useMemo, useRef } from '@wordpress/element';
import useKeyboardShortcuts from '../hooks/use-keyboard-shortcuts';
import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function NewslettersList( { navigate, notify } ) {
	const [ newsletters, setNewsletters ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const createBtnRef = useRef( null );

	useEffect( () => {
		loadNewsletters();
	}, [] );

	const loadNewsletters = async () => {
		setLoading( true );
		try {
			const data = await apiFetch( {
				path: '/swiftletter/v1/newsletters',
			} );
			setNewsletters( data );
		} catch ( err ) {
			notify( err.message || __( 'Failed to load newsletters.', 'swiftletter' ), 'error' );
		} finally {
			setLoading( false );
		}
	};

	const handleDelete = async ( id, title, index ) => {
		if ( ! window.confirm(
			/* translators: %s: newsletter title */
			__( 'Delete newsletter "%s" and all its articles? This cannot be undone.', 'swiftletter' ).replace( '%s', title )
		) ) {
			return;
		}

		const nextFocusIndex = index < newsletters.length - 1 ? index : index - 1;

		try {
			await apiFetch( {
				path: `/swiftletter/v1/newsletters/${ id }`,
				method: 'DELETE',
			} );
			notify( __( 'Newsletter deleted.', 'swiftletter' ) );
			await loadNewsletters();
			setTimeout( () => {
				if ( nextFocusIndex >= 0 ) {
					const btn = document.querySelector(
						`[data-newsletter-index="${ nextFocusIndex }"] button`
					);
					if ( btn ) { btn.focus(); return; }
				}
				createBtnRef.current?.focus();
			}, 0 );
		} catch ( err ) {
			notify( err.message || __( 'Failed to delete newsletter.', 'swiftletter' ), 'error' );
		}
	};

	// Keyboard shortcut: Alt+Shift+N creates a new newsletter.
	const shortcuts = useMemo( () => [
		{ key: 'n', handler: () => navigate( 'create-newsletter' ) },
	], [ navigate ] );
	useKeyboardShortcuts( shortcuts );

	if ( loading ) {
		return (
			<div className="swl-loading" aria-label={ __( 'Loading newsletters', 'swiftletter' ) }>
				<Spinner />
				{ __( 'Loading newsletters…', 'swiftletter' ) }
			</div>
		);
	}

	return (
		<div>
			<div className="swl-header-row">
				<h2>{ __( 'Newsletters', 'swiftletter' ) }</h2>
				<Button
					ref={ createBtnRef }
					variant="primary"
					onClick={ () => navigate( 'create-newsletter' ) }
					aria-keyshortcuts="Alt+Shift+N"
				>
					{ __( 'Create New Newsletter', 'swiftletter' ) }
					<span className="swl-shortcut-badge" aria-hidden="true">Alt+Shift+N</span>
				</Button>
			</div>

			{ newsletters.length === 0 ? (
				<div className="swl-empty">
					<p>{ __( 'No newsletters yet. Create your first one!', 'swiftletter' ) }</p>
				</div>
			) : (
				<table className="swl-table" role="table">
					<thead>
						<tr>
							<th scope="col">{ __( 'Title', 'swiftletter' ) }</th>
							<th scope="col">{ __( 'Articles', 'swiftletter' ) }</th>
							<th scope="col">{ __( 'Date', 'swiftletter' ) }</th>
							<th scope="col">{ __( 'Actions', 'swiftletter' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ newsletters.map( ( nl, index ) => (
							<tr key={ nl.id } data-newsletter-index={ index }>
								<td>
									<Button
										variant="link"
										onClick={ () => navigate( 'newsletter-detail', nl.id ) }
									>
										{ nl.title?.rendered || nl.title }
									</Button>
								</td>
								<td>{ nl.article_count ?? 0 }</td>
								<td>{ nl.date ? new Date( nl.date ).toLocaleDateString() : '—' }</td>
								<td>
									<Button
										variant="tertiary"
										isDestructive
										onClick={ () => handleDelete( nl.id, nl.title?.rendered || nl.title, index ) }
										aria-label={
											/* translators: %s: newsletter title */
											__( 'Delete %s', 'swiftletter' ).replace( '%s', nl.title?.rendered || nl.title )
										}
									>
										{ __( 'Delete', 'swiftletter' ) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
}
