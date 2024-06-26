Sitelist {#sitelist}
========

This document describes the XML format used to represent information about external sites known to a MediaWiki installation. This information about external sites is used to allow "inter-wiki" links, cross-language navigation, as well as close integration via direct access to the other site's web API or even directly to their database.

Lists of external sites can be imported and exported using the *importSites.php* and *exportSites.php* scripts. In the database, external sites are described by the `sites` and `site_ids` tables.

The formal specification of the format used by *importSites.php* and *exportSites.php* can be found in the *sitelist-1.0.xsd* file. Below is an example and a brief description of what the individual XML elements and attributes mean:

```xml
<sites version="1.0">
	<site>
		<globalid>acme.com</globalid>
		<localid type="interwiki">acme</localid>
		<group>Vendor</group>
		<path type="link">http://acme.com/</path>
		<source>meta.wikimedia.org</source>
	</site>
	<site type="mediawiki">
		<globalid>de.wikidik.example</globalid>
		<localid type="equivalent">de</localid>
		<group>Dictionary</group>
		<forward/>
		<path type="page_path">http://acme.com/</path>
	</site>
</sites>
```


The XML elements are used as follows:

- `sites`: The root element, containing a set of site tags. May have a `version` attribute with the value `1.0`.
- `site`: A site entry, representing an external website. May have a `type` attribute with one of the following values:
  + `unknown`: (default) any website
  + `mediawiki`: A MediaWiki site
- `globalid`: A unique identifier for the site. For a given site, the same unique global ID must be used across all wikis in a wiki farm (aka wiki family).
- `localid`: An identifier for the site, for use on the local wiki. Multiple local IDs may be assigned to a given site. The same local ID can be used to refer to different sites by different wikis on the same farm/family. The `localid` element may have a type attribute with one of the following values:
  + `interwiki`: Used as an "interwiki" link prefix, for creating cross-wiki links.
  + `equivalent`: Used as a "language" link prefix, for cross-linking equivalent content in different languages.
- `group`: The site group (e.g. wiki family) the site belongs to.
- `path`: A URL template for accessing resources on the site. Several paths may be defined for a given site, for accessing different kinds of resources, identified by the `type` attribute, using one of the following values:
  + `link`: Generic URL template, often the document root.
  + `page_path`: (for `mediawiki` sites) URL template for wiki pages (corresponds to the target wiki's `$wgArticlePath` setting)
  + `file_path`: (for `mediawiki` sites) URL pattern for application entry points and resources (corresponds to the target wiki's `$wgScriptPath` setting).
- `forward`: Whether using a prefix defined by a `localid` tag in the URL will cause the request to be redirected to the corresponding page on the target wiki (currently unused). E.g. whether <http://wiki.acme.com/wiki/foo:Buzz> should be forwarded to <http://wiki.foo.com/read/Buzz>. (CAVEAT: not yet implement, can be specified but has no effect)
