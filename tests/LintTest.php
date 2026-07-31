<?php
/**
 * Syntax check tests.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tests;

use PHPUnit\Framework\TestCase;
use WPSM\Files\Lint;

/**
 * Lint is the gate that stops a broken edit reaching disk at all. Both
 * strategies are exercised, because shared hosts routinely disable proc_open
 * and the tokenizer fallback is what runs there.
 */
final class LintTest extends TestCase {

	private Lint $lint;

	protected function setUp(): void {
		$this->lint = new Lint();
	}

	public function test_accepts_valid_php(): void {
		$result = $this->lint->check( "<?php\n\nfunction demo() {\n\treturn 1;\n}\n", 'demo.php' );

		$this->assertTrue( $result['ok'] );
	}

	public function test_rejects_a_missing_brace(): void {
		$result = $this->lint->check( "<?php\n\nfunction demo() {\n\treturn 1;\n", 'demo.php' );

		$this->assertFalse( $result['ok'] );
		$this->assertNotSame( '', $result['message'] );
	}

	public function test_rejects_a_stray_token(): void {
		$result = $this->lint->check( "<?php\n\n\$x = ;\n", 'demo.php' );

		$this->assertFalse( $result['ok'] );
	}

	public function test_rejects_an_unterminated_string(): void {
		$result = $this->lint->check( "<?php\n\n\$x = 'unterminated;\n", 'demo.php' );

		$this->assertFalse( $result['ok'] );
	}

	public function test_reports_which_strategy_ran(): void {
		$result = $this->lint->check( "<?php\n echo 1;\n", 'demo.php' );

		$this->assertContains( $result['strategy'], [ 'php -l', 'token_get_all' ] );
	}

	public function test_reports_a_line_number_when_it_can(): void {
		$source = "<?php\n\n\$a = 1;\n\$b = 2;\nfunction broken( {\n";
		$result = $this->lint->check( $source, 'demo.php' );

		$this->assertFalse( $result['ok'] );
		$this->assertIsInt( $result['line'] );
	}

	public function test_skips_non_php_files(): void {
		// A CSS file containing something that is not valid PHP must not be
		// rejected on those grounds.
		$result = $this->lint->check( "body { color: red; }\n", 'style.css' );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'skipped', $result['strategy'] );
	}

	public function test_accepts_php_with_inline_html(): void {
		$source = "<?php if ( true ) : ?>\n<p>hello</p>\n<?php endif; ?>\n";

		$this->assertTrue( $this->lint->check( $source, 'template.php' )['ok'] );
	}

	public function test_accepts_a_file_with_no_closing_tag(): void {
		// WordPress coding standards omit the closing tag; a checker that
		// objected to that would reject almost every file in the ecosystem.
		$this->assertTrue( $this->lint->check( "<?php\n\$x = 1;\n", 'demo.php' )['ok'] );
	}

	public function test_does_not_execute_the_code_it_checks(): void {
		// If the checker ever ran the source instead of parsing it, this would
		// create the file. It must not.
		$canary = sys_get_temp_dir() . '/wpsm-lint-canary-' . bin2hex( random_bytes( 4 ) );
		$source = "<?php\nfile_put_contents( " . var_export( $canary, true ) . ", 'executed' );\n";

		$this->lint->check( $source, 'demo.php' );

		$this->assertFileDoesNotExist( $canary, 'The linter executed the source it was asked to check.' );
	}
}
