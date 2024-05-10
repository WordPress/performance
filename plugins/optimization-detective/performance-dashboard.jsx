import { __ } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';
import { createRoot, useEffect, useState } from '@wordpress/element';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalGrid as Grid,
	ComboboxControl,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { Chart } from 'react-google-charts';

function Dashboard() {
	const options = window.performanceDashboard.options;
	const defaultPostId =
		options.find(
			( { label } ) => label === window.performanceDashboard.homeUrl
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

	const lcpData = [
		[ 'Date', 'LCP' ],
		...data.map( ( entry ) => {
			return [ entry.date, entry.webVitals.LCP ];
		} ),
	];
	const clsData = [
		[ 'Date', 'CLS' ],
		...data.map( ( entry ) => {
			return [ entry.date, entry.webVitals.CLS ];
		} ),
	];
	const inpData = [
		[ 'Date', 'INP' ],
		...data.map( ( entry ) => {
			return [ entry.date, entry.webVitals.INP ];
		} ),
	];
	const ttfbData = [
		[ 'Date', 'TTFB' ],
		...data.map( ( entry ) => {
			return [ entry.date, entry.webVitals.TTFB ];
		} ),
	];

	const showChart = data.length > 0;

	const chartOptions = {
		curveType: 'function',
		legend: { position: 'bottom' },
		vAxis: { minValue: 0 },
		colors: [ '#65B25C', '#CDE7CC' ],
	};

	return (
		<>
			<div className="metabox-holder">
				<div className="postbox ">
					<div className="inside">
						<ComboboxControl
							label={ __(
								'Choose a URL',
								'optimization-detective'
							) }
							options={
								window.performanceDashboard.options || []
							}
							value={ postId }
							onChange={ setPostId }
							allowReset={ false }
						/>
					</div>
				</div>
				<Grid columns={ 2 }>
					<div className="postbox ">
						<div className="postbox-header">
							<h2 className="hndle">
								{ __(
									'Time to First Byte (TTFB)',
									'optimization-detective'
								) }
							</h2>
						</div>
						<div className="inside">
							{ showChart ? (
								<Chart
									chartType="AreaChart"
									width="100%"
									height="400px"
									data={ ttfbData }
									options={ chartOptions }
								/>
							) : null }
						</div>
					</div>
					<div className="postbox ">
						<div className="postbox-header">
							<h2 className="hndle">
								{ __(
									'Largest Contentful Paint (LCP)',
									'optimization-detective'
								) }
							</h2>
						</div>
						<div className="inside">
							{ showChart ? (
								<Chart
									chartType="AreaChart"
									width="100%"
									height="400px"
									data={ lcpData }
									options={ chartOptions }
								/>
							) : null }
						</div>
					</div>
					<div className="postbox ">
						<div className="postbox-header">
							<h2 className="hndle">
								{ __(
									'Cumulative Layout Shift (CLS)',
									'optimization-detective'
								) }
							</h2>
						</div>
						<div className="inside">
							{ showChart ? (
								<Chart
									chartType="AreaChart"
									width="100%"
									height="400px"
									data={ clsData }
									options={ chartOptions }
								/>
							) : null }
						</div>
					</div>
					<div className="postbox ">
						<div className="postbox-header">
							<h2 className="hndle">
								{ __(
									'Interaction to Next Paint (INP)',
									'optimization-detective'
								) }
							</h2>
						</div>
						<div className="inside">
							{ showChart ? (
								<Chart
									chartType="AreaChart"
									width="100%"
									height="400px"
									data={ inpData }
									options={ chartOptions }
								/>
							) : null }
						</div>
					</div>
				</Grid>
			</div>
		</>
	);
}

domReady( () => {
	const root = createRoot(
		document.getElementById( 'od-performance-dashboard' )
	);

	root.render( <Dashboard /> );
} );
