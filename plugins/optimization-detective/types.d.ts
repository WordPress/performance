interface ElementMetrics {
	isLCP: boolean;
	isLCPCandidate: boolean;
	xpath: string;
	intersectionRatio: number;
	intersectionRect: DOMRectReadOnly;
	boundingClientRect: DOMRectReadOnly;
}

interface URLMetric {
	url: string;
	viewport: {
		width: number;
		height: number;
	};
	elements: ElementMetrics[];
}

interface URLMetricGroupStatus {
	minimumViewportWidth: number;
	complete: boolean;
}

interface Extension {
	initialize?: Function;
	finalize?: Function;
}
