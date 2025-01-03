<?php
namespace Wikimedia\Telemetry;

/**
 * A no-op tracer that creates no-op spans and persists no data.
 * Useful for scenarios where tracing is disabled.
 *
 * @since 1.43
 * @internal
 */
class NoopTracer implements TracerInterface {

	private SpanContext $noopSpanContext;
	private TracerState $tracerState;

	public function __construct() {
		$this->noopSpanContext = new SpanContext( '', '', null, '', false );
		$this->tracerState = new TracerState();
	}

	/** @inheritDoc */
	public function createSpan( string $spanName, $parentSpan = null ): SpanInterface {
		return new NoopSpan( $this->tracerState, $this->noopSpanContext );
	}

	/** @inheritDoc */
	public function createRootSpan( string $spanName ): SpanInterface {
		return new NoopSpan( $this->tracerState, $this->noopSpanContext );
	}

	/** @inheritDoc */
	public function createSpanWithParent( string $spanName, SpanContext $parentSpanContext ): SpanInterface {
		return new NoopSpan( $this->tracerState, $this->noopSpanContext );
	}

	/** @inheritDoc */
	public function shutdown(): void {
		// no-op
	}
}
