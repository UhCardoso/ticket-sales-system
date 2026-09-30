<?php

return [

    /**
     * Response time range of the simulated financial system, in milliseconds.
     */
    'min_delay_ms' => (int) env('FINANCIAL_MIN_DELAY_MS', 2000),

    'max_delay_ms' => (int) env('FINANCIAL_MAX_DELAY_MS', 5000),

    /**
     * Share of calls (0 to 1) that the simulated financial system fails.
     */
    'failure_rate' => (float) env('FINANCIAL_FAILURE_RATE', 0.2),

];
