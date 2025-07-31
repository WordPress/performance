export interface URLBatchCursor {
	provider_index: number;
	subtype_index: number;
	page_number: number;
	offset_within_page: number;
	batch_size: number;
}

export interface ViewportBreakpoint {
	width: number;
	height: number;
}

export interface URLGroup {
	url: string;
	breakpoints: ViewportBreakpoint[];
}

export interface URLBatchResponse {
	urlGroups: URLGroup[];
	cursor: URLBatchCursor | null;
	verificationToken: string;
	isDebug: boolean;
}

export interface URLPrimingTask {
	url: string;
	width: number;
	height: number;
}
