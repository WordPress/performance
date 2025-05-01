export type ViewTransitionsConfig = {
	postSelector?: string;
	globalTransitionNames?: Record< string, string >;
	postTransitionNames?: Record< string, string >;
};

export type InitViewTransitionsFunction = (
	config: ViewTransitionsConfig
) => void;

export type ExtendedWindow = Window &
	typeof globalThis & {
		plvtInitViewTransitions?: InitViewTransitionsFunction;
		navigation?: {
			activation: NavigationActivation;
		};
	};

export type PageSwapListenerFunction = ( event: PageSwapEvent ) => void;
export type PageRevealListenerFunction = ( event: PageRevealEvent ) => void;
