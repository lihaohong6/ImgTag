# ImgTag

A MediaWiki extension adding back the `<img>` tag, which was [removed in MediaWiki 1.42](https://www.mediawiki.org/wiki/Manual:$wgAllowImageTag). It also adds a new parser function that marks a file as being used on a page.

## Installation

```shell
cd extensions
git clone https://github.com/lihaohong6/ImgTag.git --depth=1
```

Then add the following to the bottom of `LocalSettings.php`
```php
wfLoadExtension( 'ImgTag' );
```

## Configuration
Variables:
- `$wgImgTagSanitizeDomain` (bool): whether the domain name in the `src` attribute should be sanitized. Default: `true`.
- `$wgImgTagDomains` (array): permitted domains in the `src` attribute. Disabled if `$wgImgTagSanitizeDomain` is set to `false`. Default: `['upload.wikimedia.org']`.
- `$wgImgTagProtocols` (array): permitted protocols for loading images. Default: `['http', 'https']`.

## Examples
Below is a basic example.
```html
<img src="https://upload.wikimedia.org/wikipedia/commons/8/80/Wikipedia-logo-v2.svg" width="100px" class="some-class" alt="Logo of Wikipedia" />
```

This example registers a file as being used on a page using the parser function `fileused`. Note that alternatives such as using [Scribunto's `exists` function](https://www.mediawiki.org/wiki/Extension:Scribunto/Lua_reference_manual#File_metadata) is considered [expensive](https://www.mediawiki.org/wiki/Manual:$wgExpensiveParserFunctionLimit).
```wikitext
<img src="{{filepath:Logo.png}}" width="50px" />
{{#fileused:Logo.png}}
```

Although `<img>` is valid HTML markup as `img` is a self-closing tag, MediaWiki cannot properly parse it, so it is not supported by this extension. All usages of the `img` tag must end with a slash (i.e. `<img ... />`.
