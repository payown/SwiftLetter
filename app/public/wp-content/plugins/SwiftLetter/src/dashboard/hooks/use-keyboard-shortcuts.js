import { useEffect } from '@wordpress/element';

/**
 * Registers Alt+Shift keyboard shortcuts on the document.
 * Automatically cleans up on unmount — shortcuts are scoped to whichever
 * view is currently rendered.
 *
 * Callers MUST wrap `shortcuts` and `arrowShortcuts` in useMemo to avoid
 * re-registering the listener on every render.
 *
 * @param {Array<{key: string, handler: Function, disabled?: boolean}>} shortcuts
 * @param {{up?: Function, down?: Function}|null} arrowShortcuts
 */
export default function useKeyboardShortcuts( shortcuts = [], arrowShortcuts = null ) {
	useEffect( () => {
		const BLOCKED = [ 'INPUT', 'TEXTAREA', 'SELECT' ];

		function handleKeyDown( event ) {
			if ( ! event.altKey || ! event.shiftKey ) return;
			if ( event.ctrlKey || event.metaKey ) return;

			const active = document.activeElement;
			if ( active && ( BLOCKED.includes( active.tagName ) || active.isContentEditable ) ) return;

			if ( event.key === 'ArrowUp' && arrowShortcuts?.up ) {
				event.preventDefault();
				arrowShortcuts.up( event );
				return;
			}
			if ( event.key === 'ArrowDown' && arrowShortcuts?.down ) {
				event.preventDefault();
				arrowShortcuts.down( event );
				return;
			}

			const key = event.key.toLowerCase();
			for ( const s of shortcuts ) {
				if ( s.disabled ) continue;
				if ( s.key === key ) {
					event.preventDefault();
					s.handler( event );
					return;
				}
			}
		}

		document.addEventListener( 'keydown', handleKeyDown );
		return () => document.removeEventListener( 'keydown', handleKeyDown );
	}, [ shortcuts, arrowShortcuts ] );
}
