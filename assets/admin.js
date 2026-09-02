( function () {
	'use strict';

	function initializeEditor() {
		if (
			! window.cniSiteFunctionsEditor ||
			! window.cniSiteFunctionsEditor.settings ||
			! window.wp ||
			! window.wp.codeEditor
		) {
			return;
		}

		window.wp.codeEditor.initialize(
			window.cniSiteFunctionsEditor.textareaId,
			window.cniSiteFunctionsEditor.settings
		);
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initializeEditor );
	} else {
		initializeEditor();
	}
}() );
