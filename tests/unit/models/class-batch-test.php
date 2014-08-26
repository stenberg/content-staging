<?php
/*
 * Include files.
 */
require_once( '../classes/models/class-batch.php' );
require_once( '../classes/models/class-post.php' );

/*
 * Import classes.
 */
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Post;

class Batch_Test extends PHPUnit_Framework_TestCase {

	protected $post_dao;
	protected $postmeta_dao;
	protected $term_dao;
	protected $user_dao;

	protected $posts;
	protected $term_relationships;
	protected $term_taxonomies;
	protected $parent_term_taxonomies;
	protected $terms;

	protected function setUp() {

		$this->posts              = array();
		$this->term_relationships = array();
		$this->term_taxonomies    = array();
		$this->terms              = array();

		// Create a mock for the Post_DAO class.
		$this->post_dao = $this->getMockBuilder( 'Me\Stenberg\Content\Staging\DB\Post_DAO' )
			->disableOriginalConstructor()
			->getMock();

		// Create a mock for the Postmeta_DAO class.
		$this->postmeta_dao = $this->getMockBuilder( 'Me\Stenberg\Content\Staging\DB\Postmeta_DAO' )
			->disableOriginalConstructor()
			->getMock();

		// Create a mock for the Term_DAO class.
		$this->term_dao = $this->getMock(
			'Me\Stenberg\Content\Staging\DB\Term_DAO',
			array(
				'get_term_relationships_by_post_ids',
				'get_term_taxonomies_by_ids',
				'get_term_taxonomy_by_term_id_taxonomy',
				'get_terms_by_ids'
			)
		);

		// Create a mock for the User_DAO class.
		$this->user_dao = $this->getMockBuilder( 'Me\Stenberg\Content\Staging\DB\User_DAO' )
			->disableOriginalConstructor()
			->getMock();

		$post_1 = new Post();
		$post_1->set_id( 34 );

		$post_2 = new Post();
		$post_2->set_id( 96 );

		$post_3 = new Post();
		$post_3->set_id( 21 );

		$this->posts[] = $post_1;
		$this->posts[] = $post_2;
		$this->posts[] = $post_3;

		/*
		 * Term relationships keeps track on the relation between an object (e.g.
		 * a post) and a term taxonomy.
		 * None of the properties are unique, e.g. many objects can have the same
		 * object_id and term_taxonomy_id.
		 */
		$term_relationship_1 = new stdClass();
		$term_relationship_1->object_id        = 96;
		$term_relationship_1->term_taxonomy_id = 5;
		$term_relationship_1->term_order       = 0;

		$term_relationship_2 = new stdClass();
		$term_relationship_2->object_id        = 96;
		$term_relationship_2->term_taxonomy_id = 2;
		$term_relationship_2->term_order       = 0;

		$term_relationship_3 = new stdClass();
		$term_relationship_3->object_id        = 96;
		$term_relationship_3->term_taxonomy_id = 9;
		$term_relationship_3->term_order       = 0;

		$term_relationship_4 = new stdClass();
		$term_relationship_4->object_id        = 21;
		$term_relationship_4->term_taxonomy_id = 12;
		$term_relationship_4->term_order       = 0;

		$term_relationship_5 = new stdClass();
		$term_relationship_5->object_id        = 21;
		$term_relationship_5->term_taxonomy_id = 7;
		$term_relationship_5->term_order       = 0;

		$this->term_relationships[] = $term_relationship_1;
		$this->term_relationships[] = $term_relationship_2;
		$this->term_relationships[] = $term_relationship_3;
		$this->term_relationships[] = $term_relationship_4;
		$this->term_relationships[] = $term_relationship_5;

		/*
		 * Term-taxonomies keeps track on what taxonomy (e.g. category,
		 * post_tag) a term belongs to. A term can belong to multiple taxonomies.
		 * Term-taxonomies also keep track on if a term-taxonomy has a parent
		 * term and how many times a specific term-taxonomy has been used.
		 * The term_taxonomy_id property is unique.
		 */
		$term_taxonomy_1 = new stdClass();
		$term_taxonomy_1->term_taxonomy_id = 9;
		$term_taxonomy_1->term_id          = 38;
		$term_taxonomy_1->taxonomy         = 'post_tag';
		$term_taxonomy_1->description      = '';
		$term_taxonomy_1->parent           = 0;
		$term_taxonomy_1->count            = 43;

		$term_taxonomy_2 = new stdClass();
		$term_taxonomy_2->term_taxonomy_id = 2;
		$term_taxonomy_2->term_id          = 24;
		$term_taxonomy_2->taxonomy         = 0;
		$term_taxonomy_2->description      = 'post_tag';
		$term_taxonomy_2->parent           = 0;
		$term_taxonomy_2->count            = 63;

		$term_taxonomy_3 = new stdClass();
		$term_taxonomy_3->term_taxonomy_id = 12;
		$term_taxonomy_3->term_id          = 38;
		$term_taxonomy_3->taxonomy         = 'category';
		$term_taxonomy_3->description      = '';
		$term_taxonomy_3->parent           = 3;
		$term_taxonomy_3->count            = 20;

		$term_taxonomy_4 = new stdClass();
		$term_taxonomy_4->term_taxonomy_id = 7;
		$term_taxonomy_4->term_id          = 21;
		$term_taxonomy_4->taxonomy         = 'category';
		$term_taxonomy_4->description      = '';
		$term_taxonomy_4->parent           = 0;
		$term_taxonomy_4->count            = 3;

		$term_taxonomy_5 = new stdClass();
		$term_taxonomy_5->term_taxonomy_id = 5;
		$term_taxonomy_5->term_id          = 41;
		$term_taxonomy_5->taxonomy         = 'category';
		$term_taxonomy_5->description      = '';
		$term_taxonomy_5->parent           = 38;
		$term_taxonomy_5->count            = 19;

		$this->term_taxonomies[] = $term_taxonomy_1;
		$this->term_taxonomies[] = $term_taxonomy_2;
		$this->term_taxonomies[] = $term_taxonomy_3;
		$this->term_taxonomies[] = $term_taxonomy_4;
		$this->term_taxonomies[] = $term_taxonomy_5;

		/*
		 * Term-taxonomies that are parents to previously defined
		 * term-taxonomies.
		 */
		$parent_term_taxonomy_1 = new stdClass();
		$parent_term_taxonomy_1->term_taxonomy_id = 12;
		$parent_term_taxonomy_1->term_id          = 38;
		$parent_term_taxonomy_1->taxonomy         = 'category';
		$parent_term_taxonomy_1->description      = '';
		$parent_term_taxonomy_1->parent           = 3;
		$parent_term_taxonomy_1->count            = 20;

		$parent_term_taxonomy_2 = new stdClass();
		$parent_term_taxonomy_2->term_taxonomy_id = 62;
		$parent_term_taxonomy_2->term_id          = 3;
		$parent_term_taxonomy_2->taxonomy         = 'category';
		$parent_term_taxonomy_2->description      = '';
		$parent_term_taxonomy_2->parent           = 8;
		$parent_term_taxonomy_2->count            = 11;

		$parent_term_taxonomy_3 = new stdClass();
		$parent_term_taxonomy_3->term_taxonomy_id = 15;
		$parent_term_taxonomy_3->term_id          = 8;
		$parent_term_taxonomy_3->taxonomy         = 'category';
		$parent_term_taxonomy_3->description      = '';
		$parent_term_taxonomy_3->parent           = 0;
		$parent_term_taxonomy_3->count            = 3;

		$this->parent_term_taxonomies[] = $parent_term_taxonomy_1;
		$this->parent_term_taxonomies[] = $parent_term_taxonomy_2;
		$this->parent_term_taxonomies[] = $parent_term_taxonomy_3;

		/*
		 * Terms contain the actual term values. The term_id and slug properties
		 * are unique.
		 */
		$term_1 = new stdClass();
		$term_1->term_id    = 3;
		$term_1->name       = 'Sweden';
		$term_1->slug       = 'sweden';
		$term_1->term_group = 0;

		$term_2 = new stdClass();
		$term_2->term_id    = 41;
		$term_2->name       = 'Limhamn';
		$term_2->slug       = 'limhamn';
		$term_2->term_group = 0;

		$term_3 = new stdClass();
		$term_3->term_id    = 38;
		$term_3->name       = 'Malmo';
		$term_3->slug       = 'malmo';
		$term_3->term_group = 0;

		$term_4 = new stdClass();
		$term_4->term_id    = 8;
		$term_4->name       = 'World';
		$term_4->slug       = 'world';
		$term_4->term_group = 0;

		$term_5 = new stdClass();
		$term_5->term_id    = 24;
		$term_5->name       = 'Stockholm';
		$term_5->slug       = 'stockholm';
		$term_5->term_group = 0;

		$term_6 = new stdClass();
		$term_6->term_id    = 21;
		$term_6->name       = 'Universe';
		$term_6->slug       = 'universe';
		$term_6->term_group = 0;

		$this->terms[] = $term_1;
		$this->terms[] = $term_2;
		$this->terms[] = $term_3;
		$this->terms[] = $term_4;
		$this->terms[] = $term_5;
		$this->terms[] = $term_6;

		// Get term relationships.
		$this->term_dao->expects( $this->once() )
			->method( 'get_term_relationships_by_post_ids' )
			->with( $this->equalTo( array( 34, 96, 21 ) ) )
			->will( $this->returnValue( $this->term_relationships ) );

		// Get term-taxonomies.
		$this->term_dao->expects( $this->once() )
			->method( 'get_term_taxonomies_by_ids' )
			->with( $this->equalTo( array( 5, 2, 9, 12, 7 ) ) )
			->will( $this->returnValue( $this->term_taxonomies ) );

		// Get parent term-taxonomies.
		$this->term_dao->expects( $this->at( 2 ) )
			->method( 'get_term_taxonomy_by_term_id_taxonomy' )
			->with( $this->equalTo( 3 ), $this->equalTo( 'category' ) )
			->will( $this->returnValue( $this->parent_term_taxonomies[1] ) );

		$this->term_dao->expects( $this->at( 3 ) )
			->method( 'get_term_taxonomy_by_term_id_taxonomy' )
			->with( $this->equalTo( 8 ), $this->equalTo( 'category' ) )
			->will( $this->returnValue( $this->parent_term_taxonomies[0] ) );

		$this->term_dao->expects( $this->at( 4 ) )
			->method( 'get_term_taxonomy_by_term_id_taxonomy' )
			->with( $this->equalTo( 3 ), $this->equalTo( 'category' ) )
			->will( $this->returnValue( $this->parent_term_taxonomies[2] ) );

		// Get terms.
		$this->term_dao->expects( $this->once() )
			->method( 'get_terms_by_ids' )
			->with( $this->equalTo( array( 38, 24, 3, 21, 41, 8 ) ) )
			->will( $this->returnValue( $this->terms ) );
	}

	/**
	 * @test
	 */
	public function get_terms() {

		/*
		 * Arrange
		 */
		$batch = new Batch( $this->post_dao, $this->postmeta_dao, $this->term_dao, $this->user_dao, null );

		/*
		 * Act
		 */
		$terms = $batch->get_terms( $this->posts );

		/*
		 * Assert
		 */
		$this->assertEquals( $terms['term_relationships'][4], $this->term_relationships[4] );
		$this->assertEquals( $terms['term_taxonomies'][4], $this->term_taxonomies[4] );
		$this->assertEquals( $terms['term_taxonomies'][10], $this->parent_term_taxonomies[2] );
		$this->assertEquals( $terms['terms'][5], $this->terms[5] );
	}
}