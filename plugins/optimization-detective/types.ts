// h/t https://stackoverflow.com/a/59801602/93579
type ExcludeProps< T > = { [ k: string ]: any } & { [ K in keyof T ]?: never };

import { onTTFB, onFCP, onLCP, onINP, onCLS } from 'web-vitals';
import {
	onTTFB as onTTFBWithAttribution,
	onFCP as onFCPWithAttribution,
	onLCP as onLCPWithAttribution,
	onINP as onINPWithAttribution,
	onCLS as onCLSWithAttribution,
} from 'web-vitals/attribution';

export interface ElementData {
	isLCP: boolean;
	isLCPCandidate: boolean;
	xpath: string;
	intersectionRatio: number;
	intersectionRect: DOMRectReadOnly;
	boundingClientRect: DOMRectReadOnly;
}

export type ExtendedElementData = ExcludeProps< ElementData >;

export interface URLMetric {
	url: string;
	viewport: {
		width: number;
		height: number;
	};
	elements: ElementData[];
}

export type ExtendedRootData = ExcludeProps< URLMetric >;

export interface URLMetricGroupStatus {
	minimumViewportWidth: number;
	maximumViewportWidth: number | null;
	complete: boolean;
}

export type OnTTFBFunction = typeof onTTFB;
export type OnFCPFunction = typeof onFCP;
export type OnLCPFunction = typeof onLCP;
export type OnINPFunction = typeof onINP;
export type OnCLSFunction = typeof onCLS;
export type OnTTFBWithAttributionFunction = typeof onTTFBWithAttribution;
export type OnFCPWithAttributionFunction = typeof onFCPWithAttribution;
export type OnLCPWithAttributionFunction = typeof onLCPWithAttribution;
export type OnINPWithAttributionFunction = typeof onINPWithAttribution;
export type OnCLSWithAttributionFunction = typeof onCLSWithAttribution;

export type LogFunction = ( ...message: any[] ) => void;

export interface Logger {
	log: LogFunction;
	warn: LogFunction;
	error: LogFunction;
}

export type InitializeArgs = {
	readonly isDebug: boolean;
	readonly logger: Logger;
	readonly onTTFB: OnTTFBFunction | OnTTFBWithAttributionFunction;
	readonly onFCP: OnFCPFunction | OnFCPWithAttributionFunction;
	readonly onLCP: OnLCPFunction | OnLCPWithAttributionFunction;
	readonly onINP: OnINPFunction | OnINPWithAttributionFunction;
	readonly onCLS: OnCLSFunction | OnCLSWithAttributionFunction;
};

export type InitializeCallback = ( args: InitializeArgs ) => Promise< void >;

export type FinalizeArgs = {
	readonly getRootData: () => URLMetric;
	readonly extendRootData: ( properties: ExtendedRootData ) => void;
	readonly getElementData: ( xpath: string ) => ElementData | null;
	readonly extendElementData: (
		xpath: string,
		properties: ExtendedElementData
	) => void;
	readonly isDebug: boolean;
	readonly logger: Logger;
};

export type FinalizeCallback = ( args: FinalizeArgs ) => Promise< void >;

export interface Extension {
	readonly name?: string;
	initialize?: InitializeCallback;
	finalize?: FinalizeCallback;
}
