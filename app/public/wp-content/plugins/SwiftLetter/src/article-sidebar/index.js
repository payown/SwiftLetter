import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { PanelBody, Button } from '@wordpress/components';

import AIRefinementPanel from './panels/ai-refinement';
import ReviewStatusPanel from './panels/review-status';
import VersionHistoryPanel from './panels/version-history';
import AudioPanel from './panels/audio';

function ReturnToBuilderNotice() {
	const params = new URLSearchParams( window.location.search );
	// Use swl_return URL param when opening from the dashboard, or fall back to
	// the newsletter ID embedded in the localized script data (covers opening an
	// article directly from the WP admin articles list).
	const returnNewsletterId =
		params.get( 'swl_return' ) ||
		( window.swiftletterData?.newsletterId
			? String( window.swiftletterData.newsletterId )
			: null );

	if ( ! returnNewsletterId || returnNewsletterId === '0' ) {
		return null;
	}

	const dashboardUrl = window.swiftletterData?.dashboardUrl;
	if ( ! dashboardUrl ) {
		return null;
	}

	const returnUrl = `${ dashboardUrl }&newsletter_id=${ encodeURIComponent( returnNewsletterId ) }`;

	return (
		<div
			style={ {
				padding: '12px 16px',
				background: '#e7f3ff',
				borderBottom: '1px solid #c8d7e1',
			} }
		>
			<Button
				variant="link"
				href={ returnUrl }
				style={ { fontWeight: 600 } }
			>
				{ __( '\u2190 Return to Newsletter Builder', 'swiftletter' ) }
			</Button>
		</div>
	);
}

function SwiftLetterSidebar() {
	const postType = useSelect( ( select ) => {
		return select( 'core/editor' ).getCurrentPostType();
	}, [] );

	// Only show for swl_article post type.
	if ( postType !== 'swl_article' ) {
		return null;
	}

	return (
		<>
			<PluginSidebarMoreMenuItem target="swiftletter-sidebar">
				{ __( 'SwiftLetter', 'swiftletter' ) }
			</PluginSidebarMoreMenuItem>

			<PluginSidebar
				name="swiftletter-sidebar"
				title={ __( 'SwiftLetter', 'swiftletter' ) }
				icon="email-alt"
			>
				<ReturnToBuilderNotice />
				<PanelBody
					title={ __( 'AI Refinement', 'swiftletter' ) }
					initialOpen={ true }
				>
					<AIRefinementPanel />
				</PanelBody>

				<PanelBody
					title={ __( 'Review Status', 'swiftletter' ) }
					initialOpen={ true }
				>
					<ReviewStatusPanel />
				</PanelBody>

				<PanelBody
					title={ __( 'Version History', 'swiftletter' ) }
					initialOpen={ false }
				>
					<VersionHistoryPanel />
				</PanelBody>

				<PanelBody
					title={ __( 'Audio', 'swiftletter' ) }
					initialOpen={ false }
				>
					<AudioPanel />
				</PanelBody>
			</PluginSidebar>
		</>
	);
}

registerPlugin( 'swiftletter-sidebar', {
	render: SwiftLetterSidebar,
} );
