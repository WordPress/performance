export type ViewTransitionAnimationConfig = {
	useGlobalTransitionNames: boolean;
	usePostTransitionNames: boolean;
};

export type ViewTransitionsConfig = {
	postSelector?: string;
	globalTransitionNames?: Record<string, string>;
	postTransitionNames?: Record<string, string>;
	animations?: Record<string, ViewTransitionAnimationConfig>;
};

export type InitViewTransitionsFunction = (
	config: ViewTransitionsConfig
) => void;

declare global {
	interface Window {
		plvtInitViewTransitions?: InitViewTransitionsFunction;
	}
}

export type PageSwapListenerFunction = (event: PageSwapEvent) => void;
export type PageRevealListenerFunction = (event: PageRevealEvent) => void;
