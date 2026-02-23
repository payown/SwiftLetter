import { useState, useMemo } from '@wordpress/element';
import useKeyboardShortcuts from '../hooks/use-keyboard-shortcuts';
import { Button, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function CreateNewsletter( { navigate, notify } ) {
	const [ title, setTitle ] = useState( '' );
	const [ submitting, setSubmitting ] = useState( false );

	const handleSubmit = async ( e ) => {
		e.preventDefault();

		if ( ! title.trim() ) {
			notify( __( 'Please enter a newsletter title.', 'swiftletter' ), 'error' );
			return;
		}

		setSubmitting( true );
		try {
			const result = await apiFetch( {
				path: '/swiftletter/v1/newsletters',
				method: 'POST',
				data: { title: title.trim() },
			} );
			notify( __( 'Newsletter created!', 'swiftletter' ) );
			navigate( 'newsletter-detail', result.id );
		} catch ( err ) {
			notify( err.message || __( 'Failed to create newsletter.', 'swiftletter' ), 'error' );
		} finally {
			setSubmitting( false );
		}
	};

	// Keyboard shortcut: Alt+Shift+B goes back to newsletters list.
	const shortcuts = useMemo( () => [
		{ key: 'b', handler: () => navigate( 'newsletters-list' ) },
	], [ navigate ] );
	useKeyboardShortcuts( shortcuts );

	return (
		<div>
			<div className="swl-header-row">
				<h2>{ __( 'Create Newsletter', 'swiftletter' ) }</h2>
			</div>

			<form onSubmit={ handleSubmit }>
				<div className="swl-form-group">
					<TextControl
						label={ __( 'Newsletter Title', 'swiftletter' ) }
						value={ title }
						onChange={ setTitle }
						required
						autoFocus
					/>
				</div>

				<div className="swl-form-actions">
					<Button
						variant="primary"
						type="submit"
						isBusy={ submitting }
						disabled={ submitting }
					>
						{ submitting
							? __( 'Creating…', 'swiftletter' )
							: __( 'Create Newsletter', 'swiftletter' )
						}
					</Button>
					<Button
						variant="tertiary"
						onClick={ () => navigate( 'newsletters-list' ) }
						disabled={ submitting }
						aria-keyshortcuts="Alt+Shift+B"
					>
						{ __( 'Cancel', 'swiftletter' ) }
						<span className="swl-shortcut-badge" aria-hidden="true">Alt+Shift+B</span>
					</Button>
				</div>
			</form>
		</div>
	);
}
