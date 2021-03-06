<?php

class DatabaseStatusTest extends WP_UnitTestCase {
	private function clearStage() {
		delete_option( Red_Database_Status::DB_UPGRADE_STAGE );
	}

	public function setUp() {
		$this->clearStage();
	}

	private function setRunningStage( $stage ) {
		$database = new Red_Database();
		$upgraders = $database->get_upgrades_for_version( '1.0' );

		$status = new Red_Database_Status();
		$status->start_upgrade( $upgraders );
		$status->set_stage( $stage );
	}

	public function testNoStageWhenNotRunning() {
		$status = new Red_Database_Status();
		$stage = $status->get_current_stage();

		$this->assertFalse( $stage );
	}

	public function testStopWhenNotRunning() {
		$status = new Red_Database_Status();
		$status->stop_update();
		$stage = $status->get_current_stage();

		$this->assertFalse( $stage );
	}

	public function testInitialReturnsStage() {
		$this->setRunningStage( 'add_title_201' );

		$status = new Red_Database_Status();
		$stage = $status->get_current_stage();

		$this->assertEquals( 'add_title_201', $stage );

		$option = get_option( Red_Database_Status::DB_UPGRADE_STAGE );

		$this->assertEquals( 'add_title_201', $option['stage'] );
		$this->assertEquals( 'add_title_201', $option['stages'][0] );
	}

	public function testStopWhenRunning() {
		$database = new Red_Database();
		$upgraders = $database->get_upgrades_for_version( '1.0' );

		$status = new Red_Database_Status();
		$status->start_upgrade( $upgraders );
		$status->stop_update();
		$stage = $status->get_current_stage();

		$this->assertFalse( $stage );
	}

	public function testSkipNotRunning() {
		$status = new Red_Database_Status();
		$status->set_next_stage();
		$stage = $status->get_current_stage();

		$this->assertFalse( $stage );
	}

	public function testSkipToNextStage() {
		red_set_options( array( 'database' => '1.0' ) );

		$this->setRunningStage( 'add_title_201' );

		$status = new Red_Database_Status();
		$status->set_next_stage();
		$stage = $status->get_current_stage();

		$this->assertEquals( 'add_group_indices_216', $stage );
	}

	public function testSkipToEnd() {
		red_set_options( array( 'database' => '1.0' ) );
		$this->setRunningStage( 'convert_title_to_text_240' );

		$status = new Red_Database_Status();
		$status->set_next_stage();
		$stage = $status->get_current_stage();

		$this->assertFalse( $stage );
	}

	public function testStatusNotRunningNoUpgrade() {
		red_set_options( array( 'database' => REDIRECTION_DB_VERSION ) );

		$status = new Red_Database_Status();
		$expected = [
			'status' => 'ok',
			'inProgress' => false,
		];

		$this->assertEquals( $expected, $status->get_json() );
	}

	public function testStatusNotRunningNeedUpgrade() {
		red_set_options( array( 'database' => '1.0' ) );

		$status = new Red_Database_Status();
		$status->start_upgrade( [] );
		$expected = [
			'inProgress' => false,
			'status' => 'need-update',
			'current' => '1.0',
			'next' => REDIRECTION_DB_VERSION,
		];
		$actual = $status->get_json();
		unset( $actual['time'] );

		$this->assertEquals( $expected, $actual );
	}

	public function testStatusNotRunningNeedInstall() {
		red_set_options( array( 'database' => '' ) );

		$status = new Red_Database_Status();
		$status->start_install( [] );
		$expected = [
			'status' => 'need-install',
			'inProgress' => false,
			'current' => '-',
			'next' => REDIRECTION_DB_VERSION,
		];
		$actual = $status->get_json();
		unset( $actual['time'] );
		unset( $actual['api'] );

		$this->assertEquals( $expected, $actual );
	}

	public function testStatusRunningWithStage() {
		red_set_options( array( 'database' => '1.0' ) );
		$this->setRunningStage( 'add_title_201' );

		$reason = 'Add titles to redirects';

		$status = new Red_Database_Status();
		$database = new Red_Database();
		$status->start_upgrade( $database->get_upgrades() );
		$status->set_ok( $reason );

		$expected = [
			'status' => 'need-update',
			'result' => 'ok',
			'inProgress' => true,
			'current' => '1.0',
			'next' => REDIRECTION_DB_VERSION,
			'complete' => 0.0,
			'reason' => $reason,
		];

		$actual = $status->get_json( $reason );
		unset( $actual['time'] );

		$this->assertEquals( $expected, $actual );
	}

	public function testStatusRunningFinish() {
		red_set_options( array( 'database' => '1.0' ) );
		$this->setRunningStage( false );

		$reason = 'Expand size of redirect titles';

		$status = new Red_Database_Status();
		$database = new Red_Database();

		$status->start_upgrade( $database->get_upgrades() );
		$status->set_ok( $reason );
		$status->finish();
		$expected = [
			'status' => 'finish-update',
			'inProgress' => false,
			'complete' => 100,
			'reason' => $reason,
		];

		$actual = $status->get_json( $reason );
		unset( $actual['time'] );

		$this->assertEquals( $expected, $actual );
	}

	public function testStatusRunningInstallFinish() {
		red_set_options( array( 'database' => '1.0' ) );
		$this->setRunningStage( false );

		$reason = 'Expand size of redirect titles';

		$status = new Red_Database_Status();
		$database = new Red_Database();

		$status->start_install( $database->get_upgrades_for_version( '' ) );
		$status->set_ok( $reason );
		$status->finish();
		$expected = [
			'status' => 'finish-install',
			'inProgress' => false,
			'complete' => 100,
			'reason' => $reason,
		];

		$actual = $status->get_json( $reason );
		unset( $actual['time'] );

		$this->assertEquals( $expected, $actual );
	}

	public function testStatusRunningError() {
		$latest = new Red_Latest_Database();
		$reason = 'this is an error';

		red_set_options( array( 'database' => '1.0' ) );
		$this->setRunningStage( 'add_title_201' );
		$status = new Red_Database_Status();
		$status->set_error( $reason );

		$expected = [
			'status' => 'need-update',
			'result' => 'error',
			'inProgress' => true,
			'current' => '1.0',
			'next' => REDIRECTION_DB_VERSION,
			'complete' => 0.0,
			'reason' => 'this is an error',
			'debug' => $latest->get_table_schema(),
		];

		$actual = $status->get_json( new WP_Error( 'error', 'this is an error' ) );
		unset( $actual['time'] );

		$this->assertEquals( $expected, $actual );
	}
}
