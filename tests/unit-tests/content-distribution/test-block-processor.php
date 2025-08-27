<?php
/**
 * Class TestContentDistributionBlockProcessor
 *
 * @package Newspack_Network
 */

namespace Test\Content_Distribution;

use Newspack_Network\Content_Distribution;
use Newspack_Network\Content_Distribution\Block_Processor;
use Newspack_Network\Content_Distribution\Outgoing_Post;

/**
 * Test the Content Distribution Block Processor.
 */
class TestBlockProcessor extends \WP_UnitTestCase {
	/**
	 * Process a paragraph block.
	 *
	 * @param array $block The block to process.
	 *
	 * @return array The processed block.
	 */
	public static function process_paragraph( $block ) {
		$block['attrs']['test'] = 'test';
		$block['innerHTML']     = '<p>Processed</p>';
		$block['innerContent']  = [ '<p>Processed</p>' ];
		return $block;
	}

	/**
	 * Test registering a block processor.
	 */
	public function test_register_block_processor() {
		Content_Distribution::register_block_processor( 'core/paragraph', [ __CLASS__, 'process_paragraph' ] );
		$block_processor = Content_Distribution::get_block_processors( 'core/paragraph' );
		$this->assertNotEmpty( $block_processor );
		$this->assertCount( 1, $block_processor );
		$this->assertInstanceOf( Block_Processor::class, $block_processor[0] );
	}

	/**
	 * Test processing a block.
	 */
	public function test_process_block() {
		Content_Distribution::register_block_processor( 'core/paragraph', [ __CLASS__, 'process_paragraph' ] );
		$block = [ 'blockName' => 'core/paragraph' ];
		$processed_block = Content_Distribution::process_block( $block );
		$this->assertEquals( 'test', $processed_block['attrs']['test'] );
		$this->assertEquals( '<p>Processed</p>', $processed_block['innerHTML'] );
		$this->assertEquals( [ '<p>Processed</p>' ], $processed_block['innerContent'] );
	}

	/**
	 * Test Outgoing_Post
	 */
	public function test_outgoing_post() {
		Content_Distribution::register_block_processor( 'core/paragraph', [ __CLASS__, 'process_paragraph' ] );

		$post = $this->factory->post->create_and_get( [ 'post_content' => '<!-- wp:paragraph --><p>Test</p><!-- /wp:paragraph -->' ] );

		$outgoing_post = new Outgoing_Post( $post );
		$payload       = $outgoing_post->get_payload();

		$this->assertEquals( '<p>Processed</p>', $payload['post_data']['content'] );
		$this->assertEquals( '<!-- wp:paragraph {"test":"test"} --><p>Processed</p><!-- /wp:paragraph -->', $payload['post_data']['raw_content'] );
	}
}
