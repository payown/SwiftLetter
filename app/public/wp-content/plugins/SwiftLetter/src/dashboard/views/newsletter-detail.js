import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import useKeyboardShortcuts from '../hooks/use-keyboard-shortcuts';
import {
	Button,
	TextControl,
	Spinner,
	Modal,
	FormFileUpload,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function NewsletterDetail( { newsletterId, navigate, notify } ) {
	const [ newsletter, setNewsletter ] = useState( null );
	const [ articles, setArticles ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ editingTitle, setEditingTitle ] = useState( false );
	const [ titleDraft, setTitleDraft ] = useState( '' );
	const [ liveMessage, setLiveMessage ] = useState( '' );

	// Add article modal.
	const [ showAddModal, setShowAddModal ] = useState( false );
	const [ newArticleTitle, setNewArticleTitle ] = useState( '' );
	const [ addingArticle, setAddingArticle ] = useState( false );

	// Publish as WordPress post.
	const [ publishingPost, setPublishingPost ] = useState( false );
	const [ publishedPostEditUrl, setPublishedPostEditUrl ] = useState( null );

	// Newsletter-level audio.
	const [ generatingNewsletterAudio, setGeneratingNewsletterAudio ] = useState( false );
	const [ newsletterHasAudio, setNewsletterHasAudio ] = useState( false );

	// Alt text for images imported from DOCX.
	const [ imagesNeedingAltText, setImagesNeedingAltText ] = useState( [] );
	const [ generatingAltText, setGeneratingAltText ] = useState( {} );

	// Review confirmation from the builder.
	const [ confirmingReviewId, setConfirmingReviewId ] = useState( null );

	const loadNewsletter = useCallback( async () => {
		setLoading( true );
		try {
			const data = await apiFetch( {
				path: `/swiftletter/v1/newsletters/${ newsletterId }`,
			} );
			setNewsletter( data );
			setArticles( data.articles || [] );
			setTitleDraft( data.title?.rendered || data.title || '' );
		} catch ( err ) {
			notify( err.message || __( 'Failed to load newsletter.', 'swiftletter' ), 'error' );
		} finally {
			setLoading( false );
		}
	}, [ newsletterId, notify ] );

	useEffect( () => {
		loadNewsletter();
	}, [ loadNewsletter ] );

	// Sync newsletter audio status whenever newsletter data loads.
	useEffect( () => {
		if ( newsletter ) {
			setNewsletterHasAudio( newsletter.has_newsletter_audio || false );
		}
	}, [ newsletter ] );

	// Title editing.
	const saveTitle = async () => {
		try {
			await apiFetch( {
				path: `/swiftletter/v1/newsletters/${ newsletterId }`,
				method: 'PUT',
				data: { title: titleDraft.trim() },
			} );
			setEditingTitle( false );
			loadNewsletter();
			notify( __( 'Title updated.', 'swiftletter' ) );
		} catch ( err ) {
			notify( err.message || __( 'Failed to update title.', 'swiftletter' ), 'error' );
		}
	};

	// Article reordering.
	const moveArticle = useCallback( async ( index, direction ) => {
		const newArticles = [ ...articles ];
		const swapIndex = index + direction;
		if ( swapIndex < 0 || swapIndex >= newArticles.length ) {
			return;
		}

		[ newArticles[ index ], newArticles[ swapIndex ] ] = [ newArticles[ swapIndex ], newArticles[ index ] ];
		setArticles( newArticles );

		setLiveMessage(
			/* translators: %1$s: article title, %2$d: new position */
			__( '%1$s moved to position %2$d', 'swiftletter' )
				.replace( '%1$s', newArticles[ swapIndex ]?.title || '' )
				.replace( '%2$d', swapIndex + 1 )
		);

		try {
			const reorderData = newArticles.map( ( a, i ) => ( { id: a.id, order: i } ) );
			await apiFetch( {
				path: '/swiftletter/v1/articles/reorder',
				method: 'POST',
				data: { order: reorderData },
			} );
		} catch ( err ) {
			// Revert on failure.
			loadNewsletter();
			notify( __( 'Failed to reorder articles.', 'swiftletter' ), 'error' );
		}
	}, [ articles, newsletterId, notify, loadNewsletter ] );

	// Arrow-key shortcut handler — finds the focused article and moves it.
	const handleArrowReorder = useCallback( ( direction ) => {
		const activeLi = document.activeElement?.closest( '[data-article-index]' );
		if ( ! activeLi ) return;
		const index = parseInt( activeLi.dataset.articleIndex, 10 );
		if ( isNaN( index ) ) return;
		moveArticle( index, direction );
		// Restore focus to the reorder button after React re-renders.
		const newIndex = index + direction;
		if ( newIndex >= 0 && newIndex < articles.length ) {
			setTimeout( () => {
				const btn = document.querySelector(
					`[data-article-index="${ newIndex }"] .swl-reorder-btn`
				);
				if ( btn ) btn.focus();
			}, 0 );
		}
	}, [ moveArticle, articles.length ] );

	// Confirm review from the builder.
	const handleConfirmReview = async ( articleId ) => {
		setConfirmingReviewId( articleId );
		try {
			await apiFetch( {
				path: `/swiftletter/v1/articles/${ articleId }/confirm-review`,
				method: 'POST',
			} );
			setArticles( ( prev ) =>
				prev.map( ( a ) =>
					a.id === articleId
						? { ...a, review_confirmed: true }
						: a
				)
			);
			notify( __( 'Article marked as reviewed.', 'swiftletter' ) );
		} catch ( err ) {
			notify( err.message || __( 'Failed to confirm review.', 'swiftletter' ), 'error' );
		} finally {
			setConfirmingReviewId( null );
		}
	};

	// Add article.
	const handleAddArticle = async () => {
		if ( ! newArticleTitle.trim() ) {
			notify( __( 'Please enter an article title.', 'swiftletter' ), 'error' );
			return;
		}

		setAddingArticle( true );
		try {
			await apiFetch( {
				path: '/swiftletter/v1/articles',
				method: 'POST',
				data: {
					title: newArticleTitle.trim(),
					newsletter_id: newsletterId,
				},
			} );
			setShowAddModal( false );
			setNewArticleTitle( '' );
			loadNewsletter();
			notify( __( 'Article created.', 'swiftletter' ) );
		} catch ( err ) {
			notify( err.message || __( 'Failed to create article.', 'swiftletter' ), 'error' );
		} finally {
			setAddingArticle( false );
		}
	};

	// DOCX upload.
	const handleDocxUpload = async ( event ) => {
		const file = event.target.files?.[ 0 ];
		if ( ! file ) {
			return;
		}

		const formData = new FormData();
		formData.append( 'file', file );
		formData.append( 'newsletter_id', newsletterId );

		try {
			const result = await apiFetch( {
				path: '/swiftletter/v1/articles/upload-docx',
				method: 'POST',
				body: formData,
				headers: {},
			} );
			loadNewsletter();
			notify( __( 'Article created from DOCX file.', 'swiftletter' ) );

			// Surface any images that were uploaded without alt text.
			if ( result?.images_needing_alt_text?.length ) {
				setImagesNeedingAltText( result.images_needing_alt_text.map( ( img ) => ( {
					...img,
					articleTitle: result.title || '',
				} ) ) );
			}
		} catch ( err ) {
			notify( err.message || __( 'Failed to upload DOCX.', 'swiftletter' ), 'error' );
		}
	};

	// Remove article.
	const removeArticle = async ( articleId, articleTitle ) => {
		if ( ! window.confirm(
			__( 'Remove article "%s" from this newsletter?', 'swiftletter' ).replace( '%s', articleTitle )
		) ) {
			return;
		}

		try {
			await apiFetch( {
				path: `/swiftletter/v1/articles/${ articleId }`,
				method: 'DELETE',
			} );
			loadNewsletter();
			notify( __( 'Article removed.', 'swiftletter' ) );
		} catch ( err ) {
			notify( err.message || __( 'Failed to remove article.', 'swiftletter' ), 'error' );
		}
	};

	// Publish newsletter as a WordPress post (draft).
	const handlePublishAsPost = async () => {
		setPublishingPost( true );
		setPublishedPostEditUrl( null );

		try {
			const data = await apiFetch( {
				path: `/swiftletter/v1/newsletters/${ newsletterId }/publish-post`,
				method: 'POST',
			} );
			setPublishedPostEditUrl( data.edit_url );
			notify( __( 'Draft post created!', 'swiftletter' ) );
		} catch ( err ) {
			notify( err.message || __( 'Failed to create post.', 'swiftletter' ), 'error' );
		} finally {
			setPublishingPost( false );
		}
	};

	// Generate AI alt text for an image attachment that came from a DOCX upload.
	const handleGenerateAltText = async ( attachmentId, articleTitle ) => {
		setGeneratingAltText( ( prev ) => ( { ...prev, [ attachmentId ]: true } ) );

		try {
			const data = await apiFetch( {
				path: `/swiftletter/v1/attachments/${ attachmentId }/generate-alt-text`,
				method: 'POST',
				data: { article_title: articleTitle },
			} );
			// Remove from the "needs alt text" list now that it's done.
			setImagesNeedingAltText( ( prev ) => prev.filter( ( img ) => img.id !== attachmentId ) );
			notify( __( 'Alt text generated and saved.', 'swiftletter' ) );
		} catch ( err ) {
			notify( err.message || __( 'Failed to generate alt text.', 'swiftletter' ), 'error' );
		} finally {
			setGeneratingAltText( ( prev ) => ( { ...prev, [ attachmentId ]: false } ) );
		}
	};

	// Generate newsletter-level audio.
	const handleGenerateNewsletterAudio = async () => {
		setGeneratingNewsletterAudio( true );
		try {
			await apiFetch( {
				path: `/swiftletter/v1/newsletters/${ newsletterId }/generate-audio`,
				method: 'POST',
			} );
			setNewsletterHasAudio( true );
			notify( __( 'Newsletter audio generated!', 'swiftletter' ) );
		} catch ( err ) {
			notify( err.message || __( 'Failed to generate newsletter audio.', 'swiftletter' ), 'error' );
		} finally {
			setGeneratingNewsletterAudio( false );
		}
	};

	// Keyboard shortcuts.
	const shortcuts = useMemo( () => [
		{ key: 'a', handler: () => setShowAddModal( true ), disabled: showAddModal },
		{ key: 'b', handler: () => navigate( 'newsletters-list' ) },
	], [ showAddModal, navigate ] );

	const arrowShortcuts = useMemo( () => ( {
		up:   () => handleArrowReorder( -1 ),
		down: () => handleArrowReorder( 1 ),
	} ), [ handleArrowReorder ] );

	useKeyboardShortcuts( shortcuts, arrowShortcuts );

	if ( loading ) {
		return (
			<div className="swl-loading">
				<Spinner />
				{ __( 'Loading newsletter…', 'swiftletter' ) }
			</div>
		);
	}

	if ( ! newsletter ) {
		return (
			<div className="swl-empty">
				<p>{ __( 'Newsletter not found.', 'swiftletter' ) }</p>
				<Button variant="secondary" onClick={ () => navigate( 'newsletters-list' ) }>
					{ __( 'Back to Newsletters', 'swiftletter' ) }
				</Button>
			</div>
		);
	}

	const unconfirmedCount = articles.filter( ( a ) => ! a.review_confirmed ).length;

	return (
		<div>
			{ /* Live region for reorder announcements */ }
			<div className="swl-sr-only" aria-live="polite" role="status">
				{ liveMessage }
			</div>

			<div className="swl-header-row">
				<Button
					variant="tertiary"
					onClick={ () => navigate( 'newsletters-list' ) }
					aria-keyshortcuts="Alt+Shift+B"
				>
					{ __( '← Back to Newsletters', 'swiftletter' ) }
					<span className="swl-shortcut-badge" aria-hidden="true">Alt+Shift+B</span>
				</Button>
			</div>

			{ /* Newsletter title */ }
			<div className="swl-header-row">
				{ editingTitle ? (
					<>
						<TextControl
							value={ titleDraft }
							onChange={ setTitleDraft }
							label={ __( 'Newsletter Title', 'swiftletter' ) }
							hideLabelFromVision
							autoFocus
							onKeyDown={ ( e ) => {
								if ( e.key === 'Enter' ) {
									saveTitle();
								}
								if ( e.key === 'Escape' ) {
									setEditingTitle( false );
								}
							} }
						/>
						<Button variant="primary" onClick={ saveTitle }>
							{ __( 'Save', 'swiftletter' ) }
						</Button>
						<Button variant="tertiary" onClick={ () => setEditingTitle( false ) }>
							{ __( 'Cancel', 'swiftletter' ) }
						</Button>
					</>
				) : (
					<>
						<h2>{ newsletter.title?.rendered || newsletter.title }</h2>
						<Button
							variant="tertiary"
							onClick={ () => setEditingTitle( true ) }
							aria-label={ __( 'Edit newsletter title', 'swiftletter' ) }
						>
							{ __( 'Edit Title', 'swiftletter' ) }
						</Button>
					</>
				) }
			</div>

			{ /* Actions bar */ }
			<div className="swl-header-row">
				<div style={ { display: 'flex', gap: '0.5rem', flexWrap: 'wrap' } }>
					<Button
						variant="secondary"
						onClick={ () => setShowAddModal( true ) }
						aria-keyshortcuts="Alt+Shift+A"
					>
						{ __( 'Add Article', 'swiftletter' ) }
						<span className="swl-shortcut-badge" aria-hidden="true">Alt+Shift+A</span>
					</Button>

					<FormFileUpload
						accept=".docx"
						onChange={ handleDocxUpload }
						variant="secondary"
						aria-label={ __( 'Upload DOCX file to create article', 'swiftletter' ) }
					>
						{ __( 'Upload DOCX', 'swiftletter' ) }
					</FormFileUpload>

					<Button
						variant="primary"
					onClick={ handlePublishAsPost }
					isBusy={ publishingPost }
					disabled={ publishingPost || articles.length === 0 || unconfirmedCount > 0 }
				>
					{ publishingPost ? __( 'Publishing…', 'swiftletter' ) : __( 'Publish as Blog Post', 'swiftletter' ) }
				</Button>

					<Button
						variant="secondary"
						onClick={ handleGenerateNewsletterAudio }
						isBusy={ generatingNewsletterAudio }
						disabled={ generatingNewsletterAudio || articles.length === 0 }
					>
						{ generatingNewsletterAudio
							? __( 'Generating Audio\u2026', 'swiftletter' )
							: newsletterHasAudio
								? __( 'Regenerate Newsletter Audio', 'swiftletter' )
								: __( 'Generate Newsletter Audio', 'swiftletter' )
						}
					</Button>

					{ newsletterHasAudio && (
						<span className="swl-badge swl-badge--has-audio">
							{ __( 'Newsletter Audio', 'swiftletter' ) }
						</span>
					) }
				</div>
			</div>

			{ publishedPostEditUrl && (
				<div className="swl-notification swl-notification--success" role="status">
					{ __( 'Draft post created: ', 'swiftletter' ) }
					<a href={ publishedPostEditUrl } target="_blank" rel="noreferrer">
						{ __( 'Edit Post →', 'swiftletter' ) }
					</a>
				</div>
			) }

			{ unconfirmedCount > 0 && (
				<div
					className="swl-notification swl-notification--error"
					role="alert"
				>
					{ __( '%d article(s) not yet reviewed. All articles must be confirmed before publishing.', 'swiftletter' )
					.replace( '%d', unconfirmedCount ) }
				</div>
			) }
			{ imagesNeedingAltText.length > 0 && (
				<div className="swl-notification swl-notification--warning" role="alert">
					<p style={ { marginBottom: '0.5rem', fontWeight: 600 } }>
						{ __( '%d image(s) imported from your document are missing alt text. Alt text is required for accessibility.', 'swiftletter' )
							.replace( '%d', imagesNeedingAltText.length ) }
					</p>
					<ul style={ { margin: 0, paddingLeft: '1.25rem' } }>
						{ imagesNeedingAltText.map( ( img ) => (
							<li key={ img.id } style={ { marginBottom: '0.25rem' } }>
								<a href={ img.edit_url } target="_blank" rel="noreferrer">
									{ __( 'Edit in Media Library', 'swiftletter' ) }
								</a>
								{ ' — ' }
								<Button
									variant="link"
									onClick={ () => handleGenerateAltText( img.id, img.articleTitle ) }
									isBusy={ generatingAltText[ img.id ] }
									disabled={ generatingAltText[ img.id ] }
								>
									{ __( 'Generate alt text with AI', 'swiftletter' ) }
								</Button>
							</li>
						) ) }
					</ul>
				</div>
			) }

			{ /* Article list */ }
			<h3>{ __( 'Articles', 'swiftletter' ) }</h3>

			{ articles.length === 0 ? (
				<div className="swl-empty">
					<p>{ __( 'No articles yet. Add one above.', 'swiftletter' ) }</p>
				</div>
			) : (
				<ul className="swl-article-list" role="list">
					{ articles.map( ( article, index ) => (
						<li key={ article.id } className="swl-article-item" data-article-index={ index }>
							<span className="swl-article-item__title">
								{ article.title }
							</span>

							{ article.review_confirmed ? (
								<span className="swl-badge swl-badge--confirmed">
									{ __( 'Reviewed', 'swiftletter' ) }
								</span>
							) : (
								<span className="swl-badge swl-badge--unconfirmed">
									{ __( 'Unreviewed', 'swiftletter' ) }
								</span>
							) }

							{ article.has_audio && (
								<span className="swl-badge swl-badge--has-audio">
									{ __( 'Audio', 'swiftletter' ) }
								</span>
							) }

							<div className="swl-article-item__actions">
								<Button
									variant="secondary"
									size="small"
									className="swl-reorder-btn"
									onClick={ () => moveArticle( index, -1 ) }
									disabled={ index === 0 }
									aria-label={
										__( 'Move %s up', 'swiftletter' ).replace( '%s', article.title )
									}
								>
									↑
								</Button>
								<Button
									variant="secondary"
									size="small"
									className="swl-reorder-btn"
									onClick={ () => moveArticle( index, 1 ) }
									disabled={ index === articles.length - 1 }
									aria-label={
										__( 'Move %s down', 'swiftletter' ).replace( '%s', article.title )
									}
								>
									↓
								</Button>
								{ ! article.review_confirmed && (
									<Button
										variant="secondary"
										size="small"
										onClick={ () => handleConfirmReview( article.id ) }
										isBusy={ confirmingReviewId === article.id }
										disabled={ confirmingReviewId === article.id }
										aria-label={
											__( 'Mark %s as reviewed', 'swiftletter' ).replace( '%s', article.title )
										}
									>
										{ __( 'Mark Reviewed', 'swiftletter' ) }
									</Button>
								) }
								<Button
									variant="secondary"
									size="small"
									href={ `${ window.swiftletterData?.editUrl || '' }${ article.id }&swl_return=${ newsletterId }` }
									aria-label={
										__( 'Edit %s', 'swiftletter' ).replace( '%s', article.title )
									}
								>
									{ __( 'Edit', 'swiftletter' ) }
								</Button>
								<Button
									variant="tertiary"
									size="small"
									isDestructive
									onClick={ () => removeArticle( article.id, article.title ) }
									aria-label={
										__( 'Remove %s', 'swiftletter' ).replace( '%s', article.title )
									}
								>
									{ __( 'Remove', 'swiftletter' ) }
								</Button>
							</div>
						</li>
					) ) }
				</ul>
			) }

			{ /* Add Article Modal */ }
			{ showAddModal && (
				<Modal
					title={ __( 'Add Article', 'swiftletter' ) }
					onRequestClose={ () => setShowAddModal( false ) }
				>
					<div className="swl-form-group">
						<TextControl
							label={ __( 'Article Title', 'swiftletter' ) }
							value={ newArticleTitle }
							onChange={ setNewArticleTitle }
							autoFocus
						/>
					</div>
					<div className="swl-form-actions">
						<Button
							variant="primary"
							onClick={ handleAddArticle }
							isBusy={ addingArticle }
							disabled={ addingArticle }
						>
							{ __( 'Create Article', 'swiftletter' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => setShowAddModal( false ) }
							disabled={ addingArticle }
						>
							{ __( 'Cancel', 'swiftletter' ) }
						</Button>
					</div>
				</Modal>
			) }

		</div>
	);
}
