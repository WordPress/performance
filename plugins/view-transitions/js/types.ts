export type ViewTransitionsConfig = {
	postSelector?: string;
	globalTransitionNames?: Record< string, string >;
	postTransitionNames?: Record< string, string >;
};

export type InitViewTransitionsFunction = (
	config: ViewTransitionsConfig
) => void;

declare global {
	interface Window {
		plvtInitViewTransitions?: InitViewTransitionsFunction;
		navigation?: {
			activation: NavigationActivation;
		};
	}
}

export type PageSwapListenerFunction = ( event: PageSwapEvent ) => void;
export type PageRevealListenerFunction = ( event: PageRevealEvent ) => void;
