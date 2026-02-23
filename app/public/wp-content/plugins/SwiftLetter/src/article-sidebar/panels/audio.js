import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { Button, SelectControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function AudioPanel() {
	const postId = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );

	const audioPath = useSelect( ( select ) => {
		return select( 'core/editor' ).getEditedPostAttribute( 'meta' )?._swl_audio_file_path;
	}, [] );

	const [ voices, setVoices ] = useState( [] );
	const [ selectedVoice, setSelectedVoice ] = useState( '' );
	const [ loadingVoices, setLoadingVoices ] = useState( true );
	const [ generating, setGenerating ] = useState( false );
	const [ previewing, setPreviewing ] = useState( false );
	const [ previewAudioUrl, setPreviewAudioUrl ] = useState( null );
	const [ articleAudioUrl, setArticleAudioUrl ] = useState( null );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		loadVoices();
	}, [] );

	useEffect( () => {
		if ( audioPath ) {
			setArticleAudioUrl(
				`${ window.swiftletterData?.restUrl || '/wp-json/swiftletter/v1/' }articles/${ postId }/audio`
			);
		}
	}, [ audioPath, postId ] );

	const loadVoices = async () => {
		setLoadingVoices( true );
		try {
			const data = await apiFetch( {
				path: '/swiftletter/v1/tts/voices',
			} );

			const voiceOptions = ( data.voices || [] ).map( ( v ) => ( {
				label: v.name || v.id || v,
				value: v.id || v,
			} ) );

			setVoices( voiceOptions );
			if ( voiceOptions.length > 0 ) {
				setSelectedVoice( voiceOptions[ 0 ].value );
			}
		} catch {
			setVoices( [] );
		} finally {
			setLoadingVoices( false );
		}
	};

	const handlePreview = async () => {
		setPreviewing( true );
		setError( null );
		setPreviewAudioUrl( null );

		try {
			const result = await apiFetch( {
				path: '/swiftletter/v1/tts/preview',
				method: 'POST',
				data: {
					voice: selectedVoice,
					text: __( 'This is a preview of the selected voice for SwiftLetter.', 'swiftletter' ),
				},
			} );

			if ( result.audio ) {
				setPreviewAudioUrl( `data:audio/mpeg;base64,${ result.audio }` );
			}
		} catch ( err ) {
			setError( err.message || __( 'Preview failed.', 'swiftletter' ) );
		} finally {
			setPreviewing( false );
		}
	};

	const handleGenerate = async () => {
		setGenerating( true );
		setError( null );

		try {
			await apiFetch( {
				path: `/swiftletter/v1/articles/${ postId }/generate-audio`,
				method: 'POST',
				data: { voice: selectedVoice },
			} );

			// Reload to pick up new audio path.
			window.location.reload();
		} catch ( err ) {
			setError( err.message || __( 'Audio generation failed.', 'swiftletter' ) );
		} finally {
			setGenerating( false );
		}
	};

	if ( loadingVoices ) {
		return (
			<div style={ { display: 'flex', alignItems: 'center', gap: '0.5rem' } }>
				<Spinner />
				{ __( 'Loading voices…', 'swiftletter' ) }
			</div>
		);
	}

	return (
		<div>
			{ voices.length > 0 && (
				<SelectControl
					label={ __( 'Voice', 'swiftletter' ) }
					value={ selectedVoice }
					options={ voices }
					onChange={ setSelectedVoice }
				/>
			) }

			<div style={ { display: 'flex', gap: '0.5rem', marginTop: '0.5rem', flexWrap: 'wrap' } }>
				<Button
					variant="secondary"
					onClick={ handlePreview }
					isBusy={ previewing }
					disabled={ previewing || ! selectedVoice }
				>
					{ previewing ? __( 'Playing…', 'swiftletter' ) : __( 'Preview Voice', 'swiftletter' ) }
				</Button>

				<Button
					variant="primary"
					onClick={ handleGenerate }
					isBusy={ generating }
					disabled={ generating || ! selectedVoice }
				>
					{ generating
						? __( 'Generating…', 'swiftletter' )
						: audioPath
							? __( 'Regenerate Audio', 'swiftletter' )
							: __( 'Generate Audio', 'swiftletter' )
					}
				</Button>
			</div>

			{ previewAudioUrl && (
				<div style={ { marginTop: '0.75rem' } }>
					<p style={ { fontSize: '13px', marginBottom: '0.25rem' } }>
						{ __( 'Voice Preview:', 'swiftletter' ) }
					</p>
					{ /* eslint-disable-next-line jsx-a11y/media-has-caption */ }
					<audio controls src={ previewAudioUrl } style={ { width: '100%' } }>
						{ __( 'Your browser does not support audio playback.', 'swiftletter' ) }
					</audio>
				</div>
			) }

			{ articleAudioUrl && (
				<div style={ { marginTop: '0.75rem' } }>
					<p style={ { fontSize: '13px', marginBottom: '0.25rem' } }>
						{ __( 'Article Audio:', 'swiftletter' ) }
					</p>
					{ /* eslint-disable-next-line jsx-a11y/media-has-caption */ }
					<audio controls src={ articleAudioUrl } style={ { width: '100%' } }>
						{ __( 'Your browser does not support audio playback.', 'swiftletter' ) }
					</audio>
				</div>
			) }

			{ error && (
				<p style={ { color: '#721c24', marginTop: '0.5rem' } } role="alert">
					{ error }
				</p>
			) }
		</div>
	);
}
