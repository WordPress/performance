export type ViewTransitionAnimationConfig = {
	useGlobalTransitionNames: boolean;
	usePostTransitionNames: boolean;
};

export type ViewTransitionsConfig = {
	postSelector?: string;
	globalTransitionNames?: Record< string, string >;
	postTransitionNames?: Record< string, string >;
	animations?: Record< string, ViewTransitionAnimationConfig >;
	paginationBase: string;
};

export type InitViewTransitionsFunction = (
	config: ViewTransitionsConfig
) => void;

export type NavigationHistoryEntry = {
	url: string | null;
};

declare global {
	interface Window {
		plvtInitViewTransitions?: InitViewTransitionsFunction;
	}
}

export type PageSwapListenerFunction = ( event: PageSwapEvent ) => void;
export type PageRevealListenerFunction = ( event: PageRevealEvent ) => void;
