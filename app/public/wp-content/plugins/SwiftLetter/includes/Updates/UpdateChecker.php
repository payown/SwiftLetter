<?php

namespace SwiftLetter\Updates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UpdateChecker {

	public static function init(): void {
		if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}

		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/payown/SwiftLetter/',
			SWIFTLETTER_FILE,
			'swiftletter'
		);

		// Use the zip file attached to each GitHub Release (built by release.yml).
		$checker->getVcsApi()->enableReleaseAssets();
	}
}
