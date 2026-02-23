import { Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function KeyboardShortcutsHelpModal( { onClose } ) {
	return (
		<Modal
			title={ __( 'Keyboard Shortcuts', 'swiftletter' ) }
			onRequestClose={ onClose }
			className="swl-shortcuts-modal"
		>
			<h3>{ __( 'Dashboard', 'swiftletter' ) }</h3>
			<table className="swl-shortcuts-table" role="table">
				<thead>
					<tr>
						<th scope="col">{ __( 'Shortcut', 'swiftletter' ) }</th>
						<th scope="col">{ __( 'Action', 'swiftletter' ) }</th>
						<th scope="col">{ __( 'Available In', 'swiftletter' ) }</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><kbd>Alt</kbd>+<kbd>Shift</kbd>+<kbd>H</kbd></td>
						<td>{ __( 'Show this help dialog', 'swiftletter' ) }</td>
						<td>{ __( 'All views', 'swiftletter' ) }</td>
					</tr>
					<tr>
						<td><kbd>Alt</kbd>+<kbd>Shift</kbd>+<kbd>N</kbd></td>
						<td>{ __( 'Create New Newsletter', 'swiftletter' ) }</td>
						<td>{ __( 'Newsletters list', 'swiftletter' ) }</td>
					</tr>
					<tr>
						<td><kbd>Alt</kbd>+<kbd>Shift</kbd>+<kbd>B</kbd></td>
						<td>{ __( 'Back to Newsletters list', 'swiftletter' ) }</td>
						<td>{ __( 'Newsletter detail, Create newsletter', 'swiftletter' ) }</td>
					</tr>
					<tr>
						<td><kbd>Alt</kbd>+<kbd>Shift</kbd>+<kbd>A</kbd></td>
						<td>{ __( 'Open Add Article dialog', 'swiftletter' ) }</td>
						<td>{ __( 'Newsletter detail', 'swiftletter' ) }</td>
					</tr>
					<tr>
						<td><kbd>Alt</kbd>+<kbd>Shift</kbd>+<kbd>X</kbd></td>
						<td>{ __( 'Open Export Newsletter dialog', 'swiftletter' ) }</td>
						<td>{ __( 'Newsletter detail', 'swiftletter' ) }</td>
					</tr>
					<tr>
						<td><kbd>Alt</kbd>+<kbd>Shift</kbd>+<kbd>↑</kbd></td>
						<td>{ __( 'Move focused article up', 'swiftletter' ) }</td>
						<td>{ __( 'Newsletter detail (article item focused)', 'swiftletter' ) }</td>
					</tr>
					<tr>
						<td><kbd>Alt</kbd>+<kbd>Shift</kbd>+<kbd>↓</kbd></td>
						<td>{ __( 'Move focused article down', 'swiftletter' ) }</td>
						<td>{ __( 'Newsletter detail (article item focused)', 'swiftletter' ) }</td>
					</tr>
				</tbody>
			</table>

			<h3>{ __( 'Article Editor Sidebar', 'swiftletter' ) }</h3>
			<table className="swl-shortcuts-table" role="table">
				<thead>
					<tr>
						<th scope="col">{ __( 'Shortcut', 'swiftletter' ) }</th>
						<th scope="col">{ __( 'Action', 'swiftletter' ) }</th>
						<th scope="col">{ __( 'Available In', 'swiftletter' ) }</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><kbd>Alt</kbd>+<kbd>Shift</kbd>+<kbd>E</kbd></td>
						<td>{ __( 'Evaluate with AI', 'swiftletter' ) }</td>
						<td>{ __( 'Article sidebar', 'swiftletter' ) }</td>
					</tr>
					<tr>
						<td><kbd>Alt</kbd>+<kbd>Shift</kbd>+<kbd>R</kbd></td>
						<td>{ __( 'Mark as Reviewed for Accuracy', 'swiftletter' ) }</td>
						<td>{ __( 'Article sidebar', 'swiftletter' ) }</td>
					</tr>
				</tbody>
			</table>

			<p className="swl-shortcuts-modal__note">
				{ __( 'Shortcuts are inactive when typing in a text field.', 'swiftletter' ) }
			</p>
		</Modal>
	);
}
