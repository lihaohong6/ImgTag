<?php

namespace MediaWiki\Extension\ImgTag;

use MediaWiki\Config\Config;
use MediaWiki\Html\Html;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class ImgTag {

	private static $config;

	public static function onParserFirstCallInit( Parser $parser ): void {
		$parser->setHook(
			'img',
			[
				self::class,
				'renderImgTag'
			]
		);
		$parser->setFunctionHook(
			'fileused',
			[
				self::class,
				'markFileAsUsed'
			]
		);
		$parser->setFunctionHook(
			'img',
			[
				self::class,
				'renderImgFunction'
			],
			Parser::SFH_OBJECT_ARGS // so we can get $frame properly
		);
	}

	public static function markFileAsUsed( Parser $parser, $filename = '' ): string {
		$filename = trim( $parser->recursiveTagParse( $filename ) );

		// Remove File: prefix if present
		if ( preg_match( '/^(File|Image):/i', $filename ) ) {
			$filename = preg_replace( '/^(File|Image):/i', '', $filename );
		}

		$title = Title::makeTitleSafe( NS_FILE, $filename );
		if ( !$title->exists() ) {
			return '';
		}

		$parser->getOutput()->addImage( $title->getDBkey() );

		return '';
	}

	public static function renderImgFunction( Parser $parser, PPFrame $frame, array $args ): string {
		$attribs = [];

		if ( isset( $args[0] ) ) {
			$attribs['src'] = $args[0];
		}

		for ( $i = 1; $i < count( $args ); $i++ ) {
			$bits = explode( '=', $frame->expand( $args[$i] ), 2 );
			if ( count( $bits ) === 2 ) {
				$attribs[trim( $bits[0] )] = trim( $bits[1] );
			}
		}

		// Reuse existing renderer
		$html = self::renderImgTag( '', $attribs, $parser, $frame );

		// This shouldn't be escaped like wikitext, so use this instead
		return $parser->insertStripItem( $html, $frame );
	}

	public static function renderImgTag( $input, array $args, Parser $parser, PPFrame $frame ): string {
		if ( self::$config === null ) {
			self::$config = MediaWikiServices::getInstance()->getMainConfig();
		}

		// Get and sanitize the src attribute
		$src = isset( $args['src'] ) ? trim( $args['src'] ) : '';
		$src = $parser->recursivePreprocess( $src, $frame );

		if ( empty( $src ) ) {
			return '<span class="error">' . wfMessage( "imgtag-error-no-src" )->inContentLanguage() . '</span>';
		}

		$sanitizeDomain = self::$config->get( "ImgTagSanitizeDomain" );
		if ( $sanitizeDomain ) {
			// Sanitize the URL
			$domains = self::$config->get( "ImgTagDomains" );
		} else {
			$domains = true;
		}
		$protocols = self::$config->get( "ImgTagProtocols" );
		[
			$sanitizedSrc,
			$sanitizationError
		] = self::sanitizeImageUrl( $src, $domains, $protocols );
		if ( $sanitizationError ) {
			return '<span class="error">' . $sanitizationError . '</span>';
		}

		$safeAttribs = [];
		$safeAttribs['src'] = $sanitizedSrc;

		// Sanitize other attributes
		$rawAttribs = [];
		$allowedAttribs = array_fill_keys( [
			'id',
			'style',
			'alt',
			'title',
			'width',
			'height',
			'class',
			'fetchpriority',
			'loading',
			'sizes'
		], true );
		foreach ( $args as $attrib => $value ) {
			if ( in_array( $attrib, $allowedAttribs ) ) {
				$value = $parser->recursivePreprocess( $value, $frame );
				$rawAttribs[$attrib] = $value;
			}
		}
		$safeAttribs = array_merge( $safeAttribs, Sanitizer::validateAttributes( $rawAttribs, $allowedAttribs ) );

		return Html::element( 'img', $safeAttribs );
	}

	/**
	 * Sanitize image URLs
	 */
	private static function sanitizeImageUrl( $url, $allowedDomains, $allowedProtocols ): array {
		// Parse the URL
		$parsed = parse_url( $url );

		if ( !$parsed ) {
			return [
				false,
				wfMessage( "imgtag-error-invalid-src" )->inContentLanguage()
			];
		}

		// Check protocol (e.g. https)
		if ( !isset( $parsed['scheme'] ) || !in_array( strtolower( $parsed['scheme'] ), $allowedProtocols ) ) {
			return [
				false,
				wfMessage( "imgtag-error-invalid-protocol" )->inContentLanguage()
			];
		}

		if ( !isset( $parsed['host'] ) ) {
			return [
				false,
				wfMessage( "imgtag-error-no-host" )->inContentLanguage()
			];
		}

		$host = strtolower( $parsed['host'] );
		if ( $allowedDomains === true ) {
			$domainAllowed = true;
		} else {
			$domainAllowed = false;
			// Check host domain
			foreach ( $allowedDomains as $allowedDomain ) {
				if (
					$host === strtolower( $allowedDomain ) || str_ends_with( $host, '.' . strtolower( $allowedDomain ) )
				) {
					$domainAllowed = true;
					break;
				}
			}
		}

		if ( !$domainAllowed ) {
			return [
				false,
				wfMessage( "imgtag-error-invalid-domain" )->inContentLanguage()
			];
		}

		return [
			$url,
			false
		];
	}
}
