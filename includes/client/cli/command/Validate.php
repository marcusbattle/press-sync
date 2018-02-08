<?php
namespace Press_Sync\client\cli\command;

use Press_Sync\client\cli\AbstractCliCommand;

use Press_Sync\client\cli\command\validate\PostValidator;
use Press_Sync\client\cli\command\validate\TaxonomyValidator;
use Press_Sync\client\cli\command\validate\Validator;
use Press_Sync\validation\Taxonomy;
use Press_Sync\API;
use WP_CLI\ExitException;

/**
 * Class Validate
 *
 * @package Press_Sync\client\cli
 * @since NEXT
 */
class Validate extends AbstractCliCommand {
	/**
	 * Set of validate subcommands.
	 *
	 * @var array
	 */
	private $subcommands = array(
		'posts' => PostValidator::class,
		'taxonomies' => TaxonomyValidator::class,
	);

	/**
	 * Register our custom commands with WP-CLI.
	 *
	 * @since NEXT
	 */
	public function register_command() {
		\WP_CLI::add_command( 'press-sync validate', array( $this, 'validate' ) );
	}

	/**
	 * Validate data consistency between source site and destination site.
	 *
	 * ## OPTIONS
	 *
	 * <validation_entity>
	 * : The type of entity to validate.
	 * options:
	 *   - posts
	 *   - taxonomies
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command associative arguments.
	 *
	 * @synopsis <validation_entity> [--remote_domain=<remote_domain>] [--remote_press_sync_key=<remote_press_sync_key>]
	 * @since NEXT

	 * @return void
	 */
	public function validate( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			\WP_CLI::warning( 'You must choose an entity type to validate.' );
			return;
		}

		$validation_entity = filter_var( $args[0], FILTER_SANITIZE_STRING );

		if ( ! isset( $this->subcommands[ $validation_entity ] ) ) {
			\WP_CLI::warning( "{$validation_entity} is not a valid entity type." );
			return;
		}

		// Call the method in this class that handles the selected entity to validate.
		/* @var $subcommand Validator */
		$subcommand = new $this->subcommands[ $validation_entity ]( $assoc_args );
		$subcommand->validate();
	}
}
