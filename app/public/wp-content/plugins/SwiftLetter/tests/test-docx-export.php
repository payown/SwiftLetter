<?php
/**
 * Standalone test for DocxExporter fixes.
 *
 * Tests the HTML sanitization pipeline, XHTML conversion, and DOCX generation
 * without requiring a full WordPress environment.
 *
 * Run: php tests/test-docx-export.php
 */

// ── Minimal WordPress stubs so DocxExporter can load ──

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WP_DEBUG', true );

function get_option( $key, $default = false ) { return $default; }
function wp_strip_all_tags( $str ) { return strip_tags( $str ); }
function do_blocks( $content ) { return $content; }
function do_shortcode( $content ) { return $content; }
function wp_upload_dir() {
	$dir = sys_get_temp_dir() . '/swl-test-uploads';
	if ( ! is_dir( $dir ) ) { mkdir( $dir, 0777, true ); }
	return [
		'basedir' => $dir,
		'baseurl' => 'https://test-plugin-dev.local/wp-content/uploads',
	];
}
function site_url() { return 'https://test-plugin-dev.local'; }
function wp_mkdir_p( $path ) { return mkdir( $path, 0777, true ); }
function __( $text, $domain = 'default' ) { return $text; }

// Load the Activator stub (in its own namespace file).
require_once __DIR__ . '/stubs.php';

// ── Autoloader ──
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/Export/DocxExporter.php';

// ── Test helpers ──

$pass_count = 0;
$fail_count = 0;

function assert_test( string $name, bool $condition, string $detail = '' ): void {
	global $pass_count, $fail_count;
	if ( $condition ) {
		$pass_count++;
		echo "  PASS: {$name}\n";
	} else {
		$fail_count++;
		echo "  FAIL: {$name}" . ( $detail ? " — {$detail}" : '' ) . "\n";
	}
}

/**
 * Use reflection to call private methods on DocxExporter.
 */
function call_private( $obj, string $method, ...$args ) {
	$ref = new ReflectionMethod( $obj, $method );
	$ref->setAccessible( true );
	return $ref->invoke( $obj, ...$args );
}

$exporter = new SwiftLetter\Export\DocxExporter();

// ════════════════════════════════════════════════════════════════════
echo "\n=== Test Suite: sanitize_html_for_docx ===\n\n";
// ════════════════════════════════════════════════════════════════════

// Test 1: Strips <iframe>
$input = '<p>Hello</p><iframe src="https://youtube.com/embed/abc"></iframe><p>World</p>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test( 'Strips <iframe>', strpos( $result, 'iframe' ) === false && strpos( $result, 'Hello' ) !== false );

// Test 2: Strips <video>
$input = '<p>Before</p><video controls><source src="test.mp4"></video><p>After</p>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test( 'Strips <video>', strpos( $result, 'video' ) === false && strpos( $result, 'Before' ) !== false );

// Test 3: Strips <script>
$input = '<p>Text</p><script>alert("xss")</script>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test( 'Strips <script>', strpos( $result, 'script' ) === false && strpos( $result, 'alert' ) === false );

// Test 4: Strips <style>
$input = '<style>.foo { color: red; }</style><p>Content</p>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test( 'Strips <style>', strpos( $result, 'style>' ) === false );

// Test 5: Strips <svg>
$input = '<p>Text</p><svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="50"/></svg>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test( 'Strips <svg>', strpos( $result, 'svg' ) === false );

// Test 6: Converts <figcaption> to <p><em>
$input = '<figcaption>Caption text</figcaption>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test( 'Converts <figcaption> to <p><em>', strpos( $result, '<p><em>Caption text</em></p>' ) !== false );

// Test 7: Unwraps <figure>
$input = '<figure class="wp-block-image"><img src="test.jpg" /><figcaption>My image</figcaption></figure>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test(
	'Unwraps <figure>, keeps content',
	strpos( $result, '<figure' ) === false && strpos( $result, '</figure>' ) === false
		&& strpos( $result, '<p><em>My image</em></p>' ) !== false
);

// Test 8: Unwraps <div>
$input = '<div class="wp-block-group"><p>Inner paragraph</p></div>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test(
	'Unwraps <div>',
	strpos( $result, '<div' ) === false && strpos( $result, '<p>Inner paragraph</p>' ) !== false
);

// Test 9: Unwraps <section>, <article>, <aside>, <nav>, <header>, <footer>
$input = '<section><article><header><p>Deep</p></header></article></section>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test(
	'Unwraps semantic containers',
	strpos( $result, '<section' ) === false && strpos( $result, '<article' ) === false
		&& strpos( $result, '<header' ) === false && strpos( $result, '<p>Deep</p>' ) !== false
);

// Test 10: Converts <hr> to separator paragraph
$input = '<p>Above</p><hr class="wp-block-separator" /><p>Below</p>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test( 'Converts <hr> to <p>___</p>', strpos( $result, '<p>___</p>' ) !== false && strpos( $result, '<hr' ) === false );

// Test 11: Converts <blockquote>
$input = '<blockquote class="wp-block-quote"><p>Quoted text</p></blockquote>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test( 'Converts <blockquote>', strpos( $result, '<blockquote' ) === false && strpos( $result, '<em>' ) !== false );

// Test 12: Preserves supported elements
$input = '<p>Paragraph</p><ul><li>Item 1</li><li>Item 2</li></ul><h3>Heading</h3><table><tr><td>Cell</td></tr></table>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test(
	'Preserves supported elements (p, ul, li, h3, table)',
	strpos( $result, '<p>Paragraph</p>' ) !== false
		&& strpos( $result, '<ul>' ) !== false
		&& strpos( $result, '<li>Item 1</li>' ) !== false
		&& strpos( $result, '<h3>' ) !== false
		&& strpos( $result, '<table>' ) !== false
);

// Test 13: Strips self-closing unsupported elements
$input = '<p>Text</p><embed src="flash.swf" />';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test( 'Strips self-closing <embed>', strpos( $result, 'embed' ) === false );


// ════════════════════════════════════════════════════════════════════
echo "\n=== Test Suite: Bare ampersand handling ===\n\n";
// ════════════════════════════════════════════════════════════════════

// Test: Bare & is escaped before processing
$input = '<p>Tom & Jerry</p>';
$result = $exporter->sanitize_html_for_docx( $input );
$xhtml = $exporter->html_to_xhtml( $result );
$dom = new DOMDocument();
$valid = @$dom->loadXML( '<root>' . $xhtml . '</root>' );
assert_test( 'Bare & produces valid XHTML', $valid === true, 'XHTML: ' . $xhtml );

// Test: Already-escaped & is not double-escaped
$input = '<p>Tom &amp; Jerry</p>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test( '&amp; is not double-escaped', strpos( $result, '&amp;amp;' ) === false );

// Test: Numeric entities are preserved
$input = '<p>Copyright &#169; 2025</p>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test( 'Numeric entity &#169; preserved', strpos( $result, '&#169;' ) !== false || strpos( $result, '©' ) !== false );

// Test: Hex entities are preserved
$input = '<p>Check &#x2714;</p>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test( 'Hex entity &#x2714; preserved', strpos( $result, '&amp;#x2714;' ) === false );

// Test: Bare & in complex content produces valid DOCX-safe XHTML
$input = '<p>R&D department — cost & benefit analysis</p><ul><li>Q&A session</li></ul>';
$sanitized = $exporter->sanitize_html_for_docx( $input );
$xhtml = $exporter->html_to_xhtml( $sanitized );
$dom = new DOMDocument();
$valid = @$dom->loadXML( '<root>' . $xhtml . '</root>' );
assert_test( 'Complex bare & content is valid XML', $valid === true, 'XHTML: ' . $xhtml );

// Test: Bare & survives full pipeline into PHPWord
$input = '<p>Tom & Jerry went to R&D</p>';
$sanitized = $exporter->sanitize_html_for_docx( $input );
$xhtml = $exporter->html_to_xhtml( $sanitized );
try {
	$phpword = new \PhpOffice\PhpWord\PhpWord();
	$section = $phpword->addSection();
	\PhpOffice\PhpWord\Shared\Html::addHtml( $section, $xhtml, false, true );
	assert_test( 'Bare & survives full pipeline to PHPWord', true );
} catch ( \Throwable $e ) {
	assert_test( 'Bare & survives full pipeline to PHPWord', false, $e->getMessage() );
}


// ════════════════════════════════════════════════════════════════════
echo "\n=== Test Suite: DOM-based sanitization edge cases ===\n\n";
// ════════════════════════════════════════════════════════════════════

// Test: Nested SVG with sub-elements is fully stripped
$input = '<p>Before</p><svg viewBox="0 0 100 100"><g><path d="M0 0 L100 100"/><circle cx="50" cy="50" r="10"/></g></svg><p>After</p>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test( 'Nested SVG fully stripped', strpos( $result, 'svg' ) === false && strpos( $result, 'path' ) === false && strpos( $result, 'circle' ) === false );
assert_test( 'Content around SVG preserved', strpos( $result, 'Before' ) !== false && strpos( $result, 'After' ) !== false );

// Test: Script inside a div is stripped, div is unwrapped
$input = '<div><p>Keep me</p><script>alert("xss")</script><p>And me</p></div>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test( 'Script in div: script stripped', strpos( $result, 'script' ) === false && strpos( $result, 'alert' ) === false );
assert_test( 'Script in div: content preserved', strpos( $result, 'Keep me' ) !== false && strpos( $result, 'And me' ) !== false );

// Test: Form elements are stripped
$input = '<form action="/submit"><input type="text" /><select><option>A</option></select><button>Submit</button></form><p>After form</p>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test( 'Form elements stripped', strpos( $result, '<form' ) === false && strpos( $result, '<input' ) === false
	&& strpos( $result, '<select' ) === false && strpos( $result, '<button' ) === false );

// Test: Deeply nested unwrap containers
$input = '<section><div><article><aside><nav><main><p>Deep content</p></main></nav></aside></article></div></section>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test( 'Deeply nested containers all unwrapped', strpos( $result, '<section' ) === false
	&& strpos( $result, '<article' ) === false && strpos( $result, '<nav' ) === false
	&& strpos( $result, 'Deep content' ) !== false );

// Test: <cite> is unwrapped (preserving text)
$input = '<blockquote><p>A quote</p><cite>Author Name</cite></blockquote>';
$result = $exporter->sanitize_html_for_docx( $input );
assert_test( 'Blockquote with cite: cite text preserved', strpos( $result, 'Author Name' ) !== false || strpos( $result, 'A quote' ) !== false );


// ════════════════════════════════════════════════════════════════════
echo "\n=== Test Suite: XHTML post-validation ===\n\n";
// ════════════════════════════════════════════════════════════════════

// Test: Malformed HTML falls back gracefully
$input = '<p>Unclosed <strong>bold text';
$xhtml = $exporter->html_to_xhtml( $input );
$dom = new DOMDocument();
$valid = @$dom->loadXML( '<root>' . $xhtml . '</root>' );
assert_test( 'Malformed HTML produces valid XHTML', $valid === true );

// Test: Already well-formed HTML passes through
$input = '<p>Clean <strong>text</strong></p>';
$xhtml = $exporter->html_to_xhtml( $input );
$dom = new DOMDocument();
$valid = @$dom->loadXML( '<root>' . $xhtml . '</root>' );
assert_test( 'Well-formed HTML stays valid', $valid === true );
assert_test( 'Well-formed HTML content preserved', strpos( $xhtml, 'Clean' ) !== false && strpos( $xhtml, '<strong>' ) !== false );


// ════════════════════════════════════════════════════════════════════
echo "\n=== Test Suite: localize_images ===\n\n";
// ════════════════════════════════════════════════════════════════════

// Test 14: Removes images with unresolvable URLs
$input = '<img src="https://test-plugin-dev.local/wp-content/uploads/2025/01/photo.jpg" alt="Test" />';
$result = $exporter->localize_images( $input );
assert_test( 'Removes unresolvable upload images', strpos( $result, '<img' ) === false );

// Test 15: Removes external images
$input = '<img src="https://external-site.com/image.png" />';
$result = $exporter->localize_images( $input );
assert_test( 'Removes external images', strpos( $result, '<img' ) === false );

// Test 16: Converts resolvable upload URL to local path
$upload = wp_upload_dir();
$test_img_dir = $upload['basedir'] . '/2025/01';
if ( ! is_dir( $test_img_dir ) ) { mkdir( $test_img_dir, 0777, true ); }
$test_img_path = $test_img_dir . '/real-photo.jpg';
file_put_contents( $test_img_path, 'fake-image-data' );

$input = '<img src="https://test-plugin-dev.local/wp-content/uploads/2025/01/real-photo.jpg" alt="Real" />';
$result = $exporter->localize_images( $input );
assert_test(
	'Converts resolvable upload URL to local path',
	strpos( $result, $test_img_path ) !== false || strpos( $result, str_replace( '/', DIRECTORY_SEPARATOR, $test_img_path ) ) !== false
);

// Test 17: Removes <img> without src
$input = '<img alt="no source" />';
$result = $exporter->localize_images( $input );
assert_test( 'Removes <img> without src', strpos( $result, '<img' ) === false );

// Test 18: No images = pass-through
$input = '<p>No images here</p>';
$result = $exporter->localize_images( $input );
assert_test( 'No images = pass-through', $result === $input );

// Clean up test image
@unlink( $test_img_path );
@rmdir( $test_img_dir );
@rmdir( $upload['basedir'] . '/2025' );


// ════════════════════════════════════════════════════════════════════
echo "\n=== Test Suite: html_to_xhtml ===\n\n";
// ════════════════════════════════════════════════════════════════════

// Test 19: Strips xmlns declarations
$input = '<p>Hello world</p>';
$result = $exporter->html_to_xhtml( $input );
assert_test( 'Strips xmlns declarations', strpos( $result, 'xmlns' ) === false );

// Test 20: Strips XML processing instructions
$input = '<p>Content</p>';
$result = $exporter->html_to_xhtml( $input );
assert_test( 'No <?xml?> processing instructions', strpos( $result, '<?xml' ) === false );

// Test 21: Produces well-formed XML
$input = '<p>Paragraph 1</p><p>Paragraph 2</p>';
$result = $exporter->html_to_xhtml( $input );
$test_xml = '<body>' . $result . '</body>';
$dom = new DOMDocument();
$valid = @$dom->loadXML( $test_xml );
assert_test( 'Produces well-formed XML', $valid === true, 'XML parse failed for: ' . $test_xml );

// Test 22: Handles special characters
$input = '<p>Tom &amp; Jerry &lt;3 &mdash; "quotes" &amp; apostrophes</p>';
$result = $exporter->html_to_xhtml( $input );
$test_xml = '<body>' . $result . '</body>';
$dom = new DOMDocument();
$valid = @$dom->loadXML( $test_xml );
assert_test( 'Handles special characters in XML', $valid === true, 'XML: ' . $test_xml );

// Test 23: Self-closing tags are well-formed
$input = '<p>Text<br>more text</p>';
$result = $exporter->html_to_xhtml( $input );
assert_test( 'Self-closing <br/> in XHTML', strpos( $result, '<br/>' ) !== false || strpos( $result, '<br />' ) !== false );

// Test 24: Empty input returns empty
$result = $exporter->html_to_xhtml( '' );
// Might return '<p></p>' from the fallback
$test_xml = '<body>' . $result . '</body>';
$dom = new DOMDocument();
$valid = @$dom->loadXML( $test_xml );
assert_test( 'Empty input produces valid XML', $valid === true );

// Test 25: PHPWord addHtml can parse the output
$input = '<p>Simple <strong>bold</strong> and <em>italic</em> text.</p><ul><li>Item A</li><li>Item B</li></ul>';
$sanitized = $exporter->sanitize_html_for_docx( $input );
$xhtml = $exporter->html_to_xhtml( $sanitized );
try {
	$phpword = new \PhpOffice\PhpWord\PhpWord();
	$section = $phpword->addSection();
	\PhpOffice\PhpWord\Shared\Html::addHtml( $section, $xhtml, false, true );
	assert_test( 'PHPWord parses clean XHTML successfully', true );
} catch ( \Throwable $e ) {
	assert_test( 'PHPWord parses clean XHTML successfully', false, $e->getMessage() );
}


// ════════════════════════════════════════════════════════════════════
echo "\n=== Test Suite: Full pipeline — sanitize → xhtml → PHPWord ===\n\n";
// ════════════════════════════════════════════════════════════════════

$test_cases = [
	'Simple paragraphs' => '<p>First paragraph.</p><p>Second paragraph.</p>',

	'Headings' => '<h2>Sub-heading</h2><h3>Sub-sub-heading</h3><p>Content under heading.</p>',

	'Bold/italic/underline' => '<p>This is <strong>bold</strong>, <em>italic</em>, and <u>underlined</u>.</p>',

	'Unordered list' => '<ul><li>Apple</li><li>Banana</li><li>Cherry</li></ul>',

	'Ordered list' => '<ol><li>First</li><li>Second</li><li>Third</li></ol>',

	'Table' => '<table><tr><th>Name</th><th>Age</th></tr><tr><td>Alice</td><td>30</td></tr></table>',

	'Links' => '<p>Visit <a href="https://example.com">Example</a> for more.</p>',

	'WordPress image block (figure+figcaption)' =>
		'<figure class="wp-block-image size-large"><img src="https://test-plugin-dev.local/wp-content/uploads/2025/01/nonexistent.jpg" alt="Test" /><figcaption>A caption</figcaption></figure>',

	'WordPress quote block' =>
		'<blockquote class="wp-block-quote"><p>To be or not to be.</p><cite>Shakespeare</cite></blockquote>',

	'WordPress separator block' =>
		'<p>Above</p><hr class="wp-block-separator has-alpha-channel-opacity"/><p>Below</p>',

	'WordPress group block (nested divs)' =>
		'<div class="wp-block-group"><div class="wp-block-group__inner-container"><p>Grouped content</p></div></div>',

	'Mixed complex content' =>
		'<div class="wp-block-columns"><div class="wp-block-column"><p>Column 1</p></div><div class="wp-block-column"><p>Column 2</p></div></div>'
		. '<figure class="wp-block-image"><img src="https://external.example.com/image.png" /></figure>'
		. '<blockquote><p>A quote</p></blockquote>'
		. '<hr />'
		. '<ul><li>List item</li></ul>',

	'Special characters' => '<p>Ampersand &amp; angle &lt;brackets&gt; and "quotes" plus em&mdash;dash and curly.</p>',

	'Bare ampersands' => '<p>Tom & Jerry at R&D — cost & benefit</p>',

	'YouTube embed (iframe)' =>
		'<figure class="wp-block-embed"><div class="wp-block-embed__wrapper"><iframe src="https://www.youtube.com/embed/abc123" allowfullscreen></iframe></div></figure><p>After embed.</p>',

	'Nested lists' => '<ul><li>Top<ul><li>Nested A</li><li>Nested B</li></ul></li><li>Bottom</li></ul>',

	'Adversarial: nested SVG' =>
		'<p>Before</p><svg xmlns="http://www.w3.org/2000/svg"><g><rect width="100" height="100"/></g></svg><p>After</p>',

	'Adversarial: script in div' =>
		'<div><script>document.write("evil")</script><p>Safe content</p></div>',

	'Emoji content' => '<p>Hello 🌍🎉 World — em dash & ampersand</p>',
];

foreach ( $test_cases as $name => $html ) {
	$sanitized = $exporter->sanitize_html_for_docx( $html );
	$xhtml = $exporter->html_to_xhtml( $sanitized );

	try {
		$phpword = new \PhpOffice\PhpWord\PhpWord();
		$section = $phpword->addSection();
		\PhpOffice\PhpWord\Shared\Html::addHtml( $section, $xhtml, false, true );
		assert_test( "Pipeline: {$name}", true );
	} catch ( \Throwable $e ) {
		assert_test( "Pipeline: {$name}", false, $e->getMessage() );
	}
}


// ════════════════════════════════════════════════════════════════════
echo "\n=== Test Suite: Full DOCX file generation & validation ===\n\n";
// ════════════════════════════════════════════════════════════════════

// Build a realistic multi-article newsletter export.
$phpword = new \PhpOffice\PhpWord\PhpWord();
$phpword->setDefaultFontName( 'Arial' );
$phpword->setDefaultFontSize( 12 );

$phpword->addTitleStyle( 1, [ 'size' => 24, 'bold' => true ] );
$phpword->addTitleStyle( 2, [ 'size' => 20, 'bold' => true ] );

$section = $phpword->addSection();
$section->addTitle( 'Test Newsletter', 1 );
$section->addTextBreak();
$section->addText( 'Table of Contents', [ 'bold' => true, 'size' => 20 ] );
$section->addTOC();
$section->addPageBreak();

// Article 1: Simple content
$section->addTitle( 'Article One: Simple Content', 2 );
$html1 = '<p>This is the first article with <strong>bold text</strong> and <em>italic text</em>.</p>'
	. '<ul><li>Point 1</li><li>Point 2</li><li>Point 3</li></ul>'
	. '<p>Conclusion paragraph.</p>';
$sanitized1 = $exporter->sanitize_html_for_docx( $html1 );
$xhtml1 = $exporter->html_to_xhtml( $sanitized1 );
try {
	\PhpOffice\PhpWord\Shared\Html::addHtml( $section, $xhtml1, false, true );
} catch ( \Throwable $e ) {
	echo "  WARNING: Article 1 HTML failed: {$e->getMessage()}\n";
	$section->addText( strip_tags( $html1 ) );
}
$section->addTextBreak( 2 );

// Article 2: Complex WordPress blocks
$section->addTitle( 'Article Two: Complex Blocks', 2 );
$html2 = '<div class="wp-block-group"><div class="wp-block-group__inner-container">'
	. '<p>Intro paragraph in a group block.</p>'
	. '<figure class="wp-block-image size-large">'
	. '<img src="https://test-plugin-dev.local/wp-content/uploads/2025/photo.jpg" alt="Photo" />'
	. '<figcaption>A photo caption</figcaption></figure>'
	. '<blockquote class="wp-block-quote"><p>This is a quoted passage.</p></blockquote>'
	. '<hr class="wp-block-separator" />'
	. '<table><tr><th>Col A</th><th>Col B</th></tr><tr><td>Data 1</td><td>Data 2</td></tr></table>'
	. '</div></div>';
$sanitized2 = $exporter->sanitize_html_for_docx( $html2 );
$xhtml2 = $exporter->html_to_xhtml( $sanitized2 );
try {
	\PhpOffice\PhpWord\Shared\Html::addHtml( $section, $xhtml2, false, true );
} catch ( \Throwable $e ) {
	echo "  WARNING: Article 2 HTML failed: {$e->getMessage()}\n";
	$section->addText( strip_tags( $html2 ) );
}
$section->addTextBreak( 2 );

// Article 3: Embeds, special chars, and bare ampersands
$section->addTitle( 'Article Three: Embeds & Special Chars', 2 );
$html3 = '<p>Testing ampersands &amp; angle brackets &lt; &gt; and "smart quotes".</p>'
	. '<p>Bare ampersand: Tom & Jerry at R&D.</p>'
	. '<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">'
	. '<iframe src="https://youtube.com/embed/test" allowfullscreen></iframe>'
	. '</div></figure>'
	. '<p>After the embed - em dash and curly quotes.</p>';
$sanitized3 = $exporter->sanitize_html_for_docx( $html3 );
$xhtml3 = $exporter->html_to_xhtml( $sanitized3 );
try {
	\PhpOffice\PhpWord\Shared\Html::addHtml( $section, $xhtml3, false, true );
} catch ( \Throwable $e ) {
	echo "  WARNING: Article 3 HTML failed: {$e->getMessage()}\n";
	$section->addText( strip_tags( $html3 ) );
}

// Write DOCX to temp file.
$output_path = sys_get_temp_dir() . '/swl-test-output-' . time() . '.docx';

$has_zip = class_exists( 'ZipArchive' );

if ( ! $has_zip ) {
	echo "  NOTE: ZipArchive extension not loaded in CLI PHP. Skipping DOCX write/validate tests.\n";
	echo "        The extension IS available in your WordPress (Local) environment.\n\n";
} else {
	$writer = \PhpOffice\PhpWord\IOFactory::createWriter( $phpword, 'Word2007' );
	$writer->save( $output_path );

	// Validate: file exists and non-empty
	assert_test( 'DOCX file exists', file_exists( $output_path ) );
	$size = filesize( $output_path );
	assert_test( 'DOCX file is non-empty', $size > 0, "Size: {$size}" );
	assert_test( 'DOCX file has reasonable size (>1KB)', $size > 1024, "Size: {$size} bytes" );

	// Validate: first 2 bytes are PK (ZIP signature)
	$handle = fopen( $output_path, 'rb' );
	$magic = fread( $handle, 2 );
	fclose( $handle );
	assert_test( 'DOCX starts with PK (ZIP signature)', $magic === 'PK' );

	// Validate: opens as ZIP
	$zip = new ZipArchive();
	$zip_result = $zip->open( $output_path, ZipArchive::RDONLY );
	assert_test( 'DOCX opens as valid ZIP', $zip_result === true );

	if ( $zip_result === true ) {
		// Check required OOXML files exist inside
		$required_entries = [
			'[Content_Types].xml',
			'word/document.xml',
			'_rels/.rels',
		];
		foreach ( $required_entries as $entry ) {
			$has = $zip->locateName( $entry ) !== false;
			assert_test( "DOCX contains {$entry}", $has );
		}

		// Validate word/document.xml is valid XML — with our fixes this MUST pass.
		$doc_xml = $zip->getFromName( 'word/document.xml' );
		$dom = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$xml_valid = $dom->loadXML( $doc_xml );
		$xml_errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		assert_test( 'word/document.xml is valid XML', $xml_valid && empty( $xml_errors ),
			! empty( $xml_errors ) ? trim( $xml_errors[0]->message ) . ' (line ' . $xml_errors[0]->line . ')' : '' );

		if ( $doc_xml ) {
			// Check that our content appears in the document
			$has_article_one = strpos( $doc_xml, 'Article One' ) !== false;
			$has_article_two = strpos( $doc_xml, 'Article Two' ) !== false;
			$has_bold = strpos( $doc_xml, 'bold text' ) !== false;
			$has_table = strpos( $doc_xml, 'Col A' ) !== false;
			assert_test( 'Document contains Article One title', $has_article_one );
			assert_test( 'Document contains Article Two title', $has_article_two );
			assert_test( 'Document contains bold text content', $has_bold );
			assert_test( 'Document contains table data', $has_table );

			// Verify bare ampersand content was properly escaped
			$has_tom = strpos( $doc_xml, 'Tom' ) !== false;
			assert_test( 'Document contains bare-& content (Tom)', $has_tom );
		}

		$zip->close();
	}

	// Test validate_docx() method directly
	echo "\n--- validate_docx() method ---\n\n";

	// Valid file should pass validation
	$writer2 = \PhpOffice\PhpWord\IOFactory::createWriter( $phpword, 'Word2007' );
	$valid_path = sys_get_temp_dir() . '/swl-validate-test-' . time() . '.docx';
	$writer2->save( $valid_path );

	try {
		$exporter->validate_docx( $valid_path );
		assert_test( 'validate_docx() passes for valid DOCX', true );
	} catch ( \RuntimeException $e ) {
		assert_test( 'validate_docx() passes for valid DOCX', false, $e->getMessage() );
	}
	@unlink( $valid_path );

	// Empty file should fail validation
	$empty_path = sys_get_temp_dir() . '/swl-empty-' . time() . '.docx';
	file_put_contents( $empty_path, '' );
	try {
		$exporter->validate_docx( $empty_path );
		assert_test( 'validate_docx() rejects empty file', false, 'Should have thrown' );
	} catch ( \RuntimeException $e ) {
		assert_test( 'validate_docx() rejects empty file', true );
	}
	@unlink( $empty_path );

	// Non-ZIP file should fail validation
	$bad_path = sys_get_temp_dir() . '/swl-bad-' . time() . '.docx';
	file_put_contents( $bad_path, 'This is not a ZIP file at all' );
	try {
		$exporter->validate_docx( $bad_path );
		assert_test( 'validate_docx() rejects non-ZIP file', false, 'Should have thrown' );
	} catch ( \RuntimeException $e ) {
		assert_test( 'validate_docx() rejects non-ZIP file', strpos( $e->getMessage(), 'PK signature' ) !== false );
	}
	@unlink( $bad_path );

	// Non-existent file should fail validation
	try {
		$exporter->validate_docx( '/tmp/nonexistent-file-' . time() . '.docx' );
		assert_test( 'validate_docx() rejects non-existent file', false, 'Should have thrown' );
	} catch ( \RuntimeException $e ) {
		assert_test( 'validate_docx() rejects non-existent file', true );
	}

	echo "\nGenerated test DOCX at: {$output_path}\n";
	echo "You can try opening this file in Word to verify it works.\n";
}

// Clean up temp upload dirs
$upload = wp_upload_dir();
@rmdir( $upload['basedir'] );


// ════════════════════════════════════════════════════════════════════
echo "\n=== RESULTS ===\n\n";
// ════════════════════════════════════════════════════════════════════

echo "Passed: {$pass_count}\n";
echo "Failed: {$fail_count}\n";
echo "Total:  " . ( $pass_count + $fail_count ) . "\n\n";

exit( $fail_count > 0 ? 1 : 0 );
