<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-CLI commands for big libraries and cron-driven maintenance.
 *
 *   wp msw scan [--no-orphans] [--duplicates]
 *   wp msw stats
 *   wp msw list [--min-size=<MB>] [--older-than=<days>] [--type=<image|video|audio|doc>] [--format=<table|csv|json>]
 *   wp msw quarantine [--min-size=<MB>] [--older-than=<days>] [--type=<type>] [--yes]
 *   wp msw delete [same filters] --yes       (permanent!)
 *   wp msw restore
 *   wp msw convert [--scope=<used|all>] [--retry]
 *   wp msw purge-originals
 */
class MSW_CLI {

	private function run_job( $type, array $opts ) {
		$r = MSW_Job::start( $type, $opts );
		if ( is_wp_error( $r ) ) {
			WP_CLI::error( $r->get_error_message() );
		}
		$last = '';
		do {
			$st = MSW_Job::tick( 25 );
			$line = sprintf( '%3d%%  %s %s', $st['pct'], $st['label'], $st['detail'] );
			if ( $line !== $last ) {
				WP_CLI::log( $line );
				$last = $line;
			}
		} while ( 'running' === $st['status'] );
		if ( 'done' !== $st['status'] ) {
			WP_CLI::error( 'Job ended with status: ' . $st['status'] . ' ' . $st['msg'] );
		}
		return $st;
	}

	public function scan( $args, $assoc ) {
		$this->run_job(
			'scan',
			array(
				'orphans'    => ! isset( $assoc['no-orphans'] ),
				'duplicates' => isset( $assoc['duplicates'] ),
			)
		);
		$this->stats();
	}

	public function stats() {
		$d = MSW_Query::dashboard();
		$s = $d['scan'];
		WP_CLI::log( "Total media:     {$s['total']} ({$s['total_size']})" );
		WP_CLI::log( "Used:            {$s['used']}" );
		WP_CLI::log( "Unused:          {$s['unused']} ({$s['unused_size']})" );
		WP_CLI::log( "Orphan files:    {$s['orphans']} ({$s['orphan_size']})" );
		WP_CLI::log( "Quarantine:      {$d['quarantine']['count']} ({$d['quarantine']['size']})" );
		WP_CLI::log( "WebP saved:      {$d['webp']['saved']} in {$d['webp']['count']} images" );
	}

	private function filters( $assoc ) {
		$f = array( 'status' => 'unused', 'per_page' => 200 );
		if ( isset( $assoc['min-size'] ) ) {
			$f['min_mb'] = $assoc['min-size'];
		}
		if ( isset( $assoc['older-than'] ) ) {
			$f['before'] = gmdate( 'Y-m-d', time() - (int) $assoc['older-than'] * DAY_IN_SECONDS );
		}
		if ( isset( $assoc['type'] ) ) {
			$f['type'] = $assoc['type'];
		}
		return $f;
	}

	/**
	 * @subcommand list
	 */
	public function list_( $args, $assoc ) {
		$f    = $this->filters( $assoc );
		$rows = array();
		for ( $p = 1; $p <= 1000; $p++ ) {
			$f['page'] = $p;
			$d         = MSW_Query::media( $f );
			foreach ( $d['items'] as $i ) {
				$rows[] = array( 'ID' => $i['id'], 'file' => $i['rel'], 'size' => $i['size'], 'uploaded' => $i['date'] );
			}
			if ( $p >= $d['pages'] ) {
				break;
			}
		}
		WP_CLI\Utils\format_items( isset( $assoc['format'] ) ? $assoc['format'] : 'table', $rows, array( 'ID', 'file', 'size', 'uploaded' ) );
	}

	private function bulk( $assoc, $permanent ) {
		$ids = MSW_Query::media_ids( $this->filters( $assoc ), 100000 );
		if ( ! $ids ) {
			WP_CLI::success( 'Nothing to do.' );
			return;
		}
		WP_CLI::confirm( sprintf( '%s %d items?', $permanent ? 'PERMANENTLY DELETE' : 'Quarantine', count( $ids ) ), $assoc );
		$ok = 0;
		foreach ( $ids as $id ) {
			$r = $permanent ? MSW_Quarantine::delete_now( $id ) : MSW_Quarantine::quarantine( $id );
			if ( true === $r ) {
				$ok++;
			} else {
				WP_CLI::warning( "#$id: $r" );
			}
		}
		MSW_Scanner::summarize();
		WP_CLI::success( "$ok done." );
	}

	public function quarantine( $args, $assoc ) {
		$this->bulk( $assoc, false );
	}

	public function delete( $args, $assoc ) {
		$this->bulk( $assoc, true );
	}

	public function restore() {
		MSW_Quarantine::restore_all();
		WP_CLI::success( 'Restored.' );
	}

	public function convert( $args, $assoc ) {
		$st = $this->run_job(
			'convert',
			array(
				'scope' => isset( $assoc['scope'] ) ? $assoc['scope'] : MSW_Util::get( 'webp_scope' ),
				'retry' => isset( $assoc['retry'] ),
			)
		);
		$c = $st['counts'];
		WP_CLI::success( sprintf( 'Converted %d, skipped %d, failed %d, saved %s', $c['converted'], $c['skipped'], $c['failed'], MSW_Util::size( $c['saved'] ) ) );
	}

	/**
	 * @subcommand purge-originals
	 */
	public function purge_originals() {
		WP_CLI::success( MSW_Converter::purge_originals( true ) . ' images cleaned.' );
	}
}
