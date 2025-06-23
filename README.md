# ImgTag

A MediaWiki extension adding back the `<img>` tag, which was [removed in MediaWiki 1.42](https://www.mediawiki.org/wiki/Manual:$wgAllowImageTag). 

## Installation

```shell
cd extensions
git clone https://github.com/lihaohong6/ImgTag.git --depth=1
```

Then add the following to the bottom of `LocalSettings.php`
```php
wfLoadExtension( 'PortableInfobox' );
```

## Configuration
Variables:
- `$wgImgTagSanitizeDomain` (bool): whether the domain name in the `src` attribute should be sanitized.
- `$wgImgTagDomains` (array): permitted domains in the `src` attribute. Disabled if `$wgImgTagSanitizeDomain` is set to `false`.
- `$wgImgTagProtocols` (array): permitted protocols for loading images.

## Examples
```html
<img src="https://upload.wikimedia.org/wikipedia/commons/8/80/Wikipedia-logo-v2.svg" width="100px" class="some-class" alt="Logo of Wikipedia" />
```

This example registers a file as used on a page.
```wikitext
<img src="{{filepath:Logo.png}}" width="50px" />
{{#fileused:Logo.png}}
```
