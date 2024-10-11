interface ElementData {
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
	elements: ElementData[];
}

interface URLMetricGroupStatus {
	minimumViewportWidth: number;
	complete: boolean;
}

type InitializeArgs = {
	readonly isDebug: boolean,
};

type InitializeCallback = ( args: InitializeArgs ) => void;

type FinalizeArgs = {
	readonly getRootData: () => URLMetric,
	readonly amendRootData: ( properties: object ) => void,
	readonly getElementData: ( xpath: string ) => ElementData|null,
	readonly amendElementData: ( xpath: string, properties: object ) => boolean,
	readonly isDebug: boolean,
};

type FinalizeCallback = ( args: FinalizeArgs ) => void;

interface Extension {
	initialize?: InitializeCallback;
	finalize?: FinalizeCallback;
}
