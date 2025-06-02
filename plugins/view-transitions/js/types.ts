export interface ViewTransitionAnimationConfig {
	useGlobalTransitionNames: boolean;
	usePostTransitionNames: boolean;
}

export interface ViewTransitionsConfig {
	postSelector?: string;
	globalTransitionNames?: Record< string, string >;
	postTransitionNames?: Record< string, string >;
	animations: Record< string, ViewTransitionAnimationConfig >;
}

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
