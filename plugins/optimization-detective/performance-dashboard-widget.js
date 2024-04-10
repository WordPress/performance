import { __ } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';
import { createRoot, useEffect, useState } from '@wordpress/element';
import { ComboboxControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

/**
 * Calculate the nth percentile of an array.
 *
 * @param {Array}  arr The array to calculate the percentile of.
 * @param {number} p   The percentile to calculate (0-1).
 * @return {number} The nth percentile of the array.
 */
function percentile( arr, p ) {
	if ( arr.length === 0 ) {
		return 0;
	}

	const index = ( arr.length - 1 ) * p;
	const lower = Math.floor( index );
	const upper = lower + 1;
	const weight = index % 1;

	if ( upper >= arr.length ) {
		return arr[ lower ];
	}
	return arr[ lower ] * ( 1 - weight ) + arr[ upper ] * weight;
}

/*
 See:
  - https://web.dev/articles/lcp
  - https://web.dev/articles/cls
  - https://web.dev/articles/inp
  - https://web.dev/articles/ttfb
*/

const THRESHOLDS = {
	LCP: {
		good: 2500,
		poor: 4000,
		percentile: 0.75,
	},
	CLS: {
		good: 0.1,
		poor: 0.25,
		percentile: 0.75,
	},
	INP: {
		good: 200,
		poor: 500,
		percentile: 0.75,
	},
	TTFB: {
		good: 800,
		poor: 1800,
		percentile: 0.75,
	},
};

const COLOR_MAP = {
	good: '#5ECB75',
	poor: '#EC5C4C',
	neutral: '#F2A83B',
};

function round( num, decimals = 0 ) {
	const p = Math.pow( 10, decimals );
	const n = num * p * ( 1 + Number.EPSILON );
	return Math.round( n ) / p;
}

function ColoredMetric( { metric, value, unit } ) {
	const good = THRESHOLDS[ metric ].good;
	const poor = THRESHOLDS[ metric ].poor;

	// eslint-disable-next-line no-nested-ternary
	const color = value <= good ? 'good' : value <= poor ? 'poor' : 'neutral';

	return (
		<div
			style={ {
				backgroundColor: COLOR_MAP[ color ],
			} }
		>
			{ unit ? (
				<>
					{ value } <span>{ unit }</span>
				</>
			) : (
				value
			) }
		</div>
	);
}

function DashboardWidget() {
	const options = window.performanceDashboardWidget.options;
	const defaultPostId =
		options.find(
			( { label } ) => label === window.performanceDashboardWidget.homeUrl
		)?.value ||
		options[ 0 ]?.value ||
		null;

	const [ postId, setPostId ] = useState( defaultPostId );
	const [ data, setData ] = useState( [] );

	useEffect( () => {
		if ( ! postId ) {
			setData( null );
			return;
		}

		apiFetch( {
			path: `/optimization-detective/v1/url-metrics:chart/${ postId }`,
		} ).then( ( response ) => {
			setData( response );
		} );
	}, [ postId ] );

	const lcpData = data.map( ( entry ) => entry.webVitals.LCP );
	const lcpMetric = percentile( lcpData, THRESHOLDS.LCP.percentile );

	const inpData = data.map( ( entry ) => entry.webVitals.INP );
	const inpMetric = percentile( inpData, THRESHOLDS.INP.percentile );

	const ttfbData = data.map( ( entry ) => entry.webVitals.TTFB );
	const ttfbMetric = percentile( ttfbData, THRESHOLDS.TTFB.percentile );

	const clsData = data.map( ( entry ) => entry.webVitals.CLS );
	const clsMetric = percentile( clsData, THRESHOLDS.CLS.percentile );

	console.log( lcpData, lcpMetric );

	return (
		<>
			<div>
				<ComboboxControl
					label={ __( 'Choose a URL', 'optimization-detective' ) }
					options={ window.performanceDashboardWidget.options || [] }
					value={ postId }
					onChange={ setPostId }
					allowReset={ false }
				/>
			</div>
			<div>
				<table style={ { width: '100%' } }>
					<tbody>
						<tr style={ { textAlign: 'left' } }>
							<th>
								{ __(
									'Time to First Byte (TTFB)',
									'optimization-detective'
								) }
							</th>
							<td>
								<ColoredMetric
									metric="TTFB"
									value={ round( ttfbMetric ) }
									unit="ms"
								/>
							</td>
						</tr>
						<tr style={ { textAlign: 'left' } }>
							<th>
								{ __(
									'Largest Contentful Paint (LCP)',
									'optimization-detective'
								) }
							</th>
							<td>
								<ColoredMetric
									metric="LCP"
									value={ round( lcpMetric ) }
									unit="ms"
								/>
							</td>
						</tr>
						<tr style={ { textAlign: 'left' } }>
							<th>
								{ __(
									'Cumulative Layout Shift (CLS)',
									'optimization-detective'
								) }
							</th>
							<td>
								<ColoredMetric
									metric="CLS"
									value={ round( clsMetric, 2 ) }
								/>
							</td>
						</tr>
						<tr style={ { textAlign: 'left' } }>
							<th>
								{ __(
									'Interaction to Next Paint (INP)',
									'optimization-detective'
								) }
							</th>
							<td>
								<ColoredMetric
									metric="INP"
									value={ round( inpMetric ) }
									unit="ms"
								/>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</>
	);
}

domReady( () => {
	const root = createRoot(
		document.getElementById( 'od-performance-dashboard-widget' )
	);

	root.render( <DashboardWidget /> );
} );
