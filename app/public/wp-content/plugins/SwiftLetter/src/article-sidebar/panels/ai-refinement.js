import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function AIRefinementPanel() {
	const postId = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );
	const { editPost } = useDispatch( 'core/editor' );
	const { savePost } = useDispatch( 'core/editor' );

	const [ refining, setRefining ] = useState( false );
	const [ diff, setDiff ] = useState( null );
	const [ error, setError ] = useState( null );

	const handleRefine = async () => {
		setRefining( true );
		setDiff( null );
		setError( null );

		// Save the post first to ensure latest content is sent.
		try {
			await savePost();
		} catch {
			// Continue even if save fails — the API uses saved content.
		}

		try {
			const result = await apiFetch( {
				path: `/swiftletter/v1/articles/${ postId }/ai-refine`,
				method: 'POST',
			} );

			setDiff( {
				original: result.original,
				refined: result.refined,
			} );
		} catch ( err ) {
			setError( err.message || __( 'AI refinement failed.', 'swiftletter' ) );
		} finally {
			setRefining( false );
		}
	};

	// Keyboard shortcut: Alt+Shift+E triggers AI evaluation.
	useEffect( () => {
		function onKeyDown( event ) {
			if ( ! event.altKey || ! event.shiftKey || event.ctrlKey || event.metaKey ) return;
			if ( event.key.toLowerCase() !== 'e' ) return;
			const a = document.activeElement;
			if ( a && ( [ 'INPUT', 'TEXTAREA', 'SELECT' ].includes( a.tagName ) || a.isContentEditable ) ) return;
			event.preventDefault();
			if ( ! refining ) handleRefine();
		}
		document.addEventListener( 'keydown', onKeyDown );
		return () => document.removeEventListener( 'keydown', onKeyDown );
	}, [ refining, handleRefine ] );

	const acceptChanges = () => {
		editPost( { content: diff.refined } );
		setDiff( null );
	};

	const rejectChanges = () => {
		setDiff( null );
	};

	return (
		<div>
			<p>
				{ __( 'Use AI to refine article text for grammar, readability, and clarity.', 'swiftletter' ) }
			</p>

			<Button
				variant="secondary"
				onClick={ handleRefine }
				isBusy={ refining }
				disabled={ refining }
				aria-keyshortcuts="Alt+Shift+E"
			>
				{ refining
					? __( 'Evaluating…', 'swiftletter' )
					: __( 'Evaluate with AI', 'swiftletter' )
				}
			</Button>

			{ error && (
				<p style={ { color: '#721c24', marginTop: '0.5rem' } } role="alert">
					{ error }
				</p>
			) }

			{ diff && (
				<div style={ { marginTop: '1rem' } }>
					<h4>{ __( 'AI Suggestions', 'swiftletter' ) }</h4>

					<div
						style={ {
							maxHeight: '300px',
							overflow: 'auto',
							border: '1px solid #ddd',
							padding: '0.75rem',
							borderRadius: '4px',
							fontSize: '14px',
							lineHeight: '1.6',
							marginBottom: '0.75rem',
						} }
						aria-label={ __( 'Refined content preview', 'swiftletter' ) }
						// eslint-disable-next-line react/no-danger
						dangerouslySetInnerHTML={ {
							__html: sanitizeForPreview( diff.refined ),
						} }
					/>

					<div style={ { display: 'flex', gap: '0.5rem' } }>
						<Button
							variant="primary"
							onClick={ acceptChanges }
						>
							{ __( 'Accept Changes', 'swiftletter' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ rejectChanges }
						>
							{ __( 'Reject', 'swiftletter' ) }
						</Button>
					</div>
				</div>
			) }
		</div>
	);
}

/**
 * Basic sanitization for preview display.
 * WordPress block content contains limited HTML.
 */
function sanitizeForPreview( html ) {
	const div = document.createElement( 'div' );
	div.textContent = html;
	// Return escaped text content, re-allowing basic HTML tags.
	return html
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /&lt;(\/?(p|h[1-6]|strong|em|a|ul|ol|li|br|blockquote)[^&]*?)&gt;/gi, '<$1>' );
}
