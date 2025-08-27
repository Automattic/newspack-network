<?php
/**
 * Content Distribution Block Processor
 *
 * @package Newspack_Network
 */

namespace Newspack_Network\Content_Distribution;

/**
 * Block processor class.
 */
class Block_Processor {

	/**
	 * The name of the block to process.
	 *
	 * @var string
	 */
	protected $block_name;

	/**
	 * The callback to transform the block.
	 *
	 * @var callable
	 */
	protected $transform_callback;

	/**
	 * Constructor.
	 *
	 * @param string   $block_name         The name of the block to process.
	 * @param callable $transform_callback The callback to transform the block.
	 */
	public function __construct( $block_name, $transform_callback ) {
		$this->block_name         = $block_name;
		$this->transform_callback = $transform_callback;
	}

	/**
	 * Process a block.
	 *
	 * @param array $block The block to process.
	 *
	 * @return array The processed block.
	 */
	public function process_block( $block ) {
		return call_user_func( $this->transform_callback, $block );
	}
}
