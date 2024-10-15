
// h/t https://stackoverflow.com/a/59801602/93579
type ExcludeProps<T> = { [k: string]: any } & { [K in keyof T]?: never }

export interface ElementData {
	isLCP: boolean;
	isLCPCandidate: boolean;
	xpath: string;
	intersectionRatio: number;
	intersectionRect: DOMRectReadOnly;
	boundingClientRect: DOMRectReadOnly;
}

export type ExtendedElementData = ExcludeProps<ElementData>

export interface URLMetric {
	url: string;
	viewport: {
		width: number;
		height: number;
	};
	elements: ElementData[];
}

export type ExtendedRootData = ExcludeProps<URLMetric>

export interface URLMetricGroupStatus {
	minimumViewportWidth: number;
	complete: boolean;
}

export type InitializeArgs = {
	readonly isDebug: boolean,
};

export type InitializeCallback = ( args: InitializeArgs ) => void;

export type FinalizeArgs = {
	readonly getRootData: () => URLMetric,
	readonly extendRootData: ( properties: ExtendedRootData ) => void,
	readonly getElementData: ( xpath: string ) => ElementData|null,
	readonly extendElementData: (xpath: string, properties: ExtendedElementData ) => void,
	readonly isDebug: boolean,
};

export type FinalizeCallback = ( args: FinalizeArgs ) => Promise<void>;

export interface Extension {
	initialize?: InitializeCallback;
	finalize?: FinalizeCallback;
}
