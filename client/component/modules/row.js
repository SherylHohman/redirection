/* global Redirectioni10n */
/**
 * External dependencies
 */

import React from 'react';
import { translate as __ } from 'lib/locale';
import { connect } from 'react-redux';

/**
 * Internal dependencies
 */

import RowActions from 'component/table/row-action';
import { getModule, setModule, downloadFile } from 'state/module/action';
import ApacheConfigure from './apache';
import ModuleData from './data';

const MODULES = {
	wordpress: 'WordPress',
	apache: 'Apache',
	nginx: 'Nginx',
};
const EXPORTS = {
	wordpress: [ 'rss', 'csv', 'apache', 'nginx' ],
	apache: [ 'csv', 'apache', 'nginx', 'config' ],
	nginx: [ 'csv', 'apache', 'nginx' ],
};
const EXPORT_NAME = {
	rss: 'RSS',
	csv: 'CSV',
	apache: 'Apache',
	nginx: 'Nginx',
};
const MODULE_ID = {
	wordpress: 1,
	apache: 2,
	nginx: 3,
};
const DESCRIPTIONS = {
	wordpress: __( 'WordPress-powered redirects. This requires no further configuration, and you can track hits.' ),
	apache: __( 'Uses Apache {{code}}.htaccess{{/code}} files. Requires further configuration. The redirect happens without loading WordPress. No tracking of hits.', {
		components: {
			code: <code />,
		}
	} ),
	nginx: __( 'For use with Nginx server. Requires manual configuration. The redirect happens without loading WordPress. No tracking of hits. This is an experimental module.' ),
};

const moduleName = name => MODULES[ name ] ? MODULES[ name ] : '';
const description = name => DESCRIPTIONS[ name ] ? DESCRIPTIONS[ name ] : '';
const getUrl = ( modId, modType ) => Redirectioni10n.pluginRoot + '&sub=modules&export=' + MODULE_ID[ modId ] + '&exporter=' + modType;

const exporter = ( modType, modName, pos, getData ) => {
	const url = getUrl( modName, modType );
	const clicker = ev => {
		ev.preventDefault();
		getData( modName, modType );
	};

	if ( modType === 'config' ) {
		return <a key={ pos } href={ url } onClick={ clicker }>{ __( 'Configure' ) }</a>;
	} else if ( modType === 'rss' ) {
		return <a key={ pos } href={ Redirectioni10n.pluginRoot + '&sub=rss&module=1&token=' + Redirectioni10n.token }>RSS</a>;
	}

	return <a key={ pos } href={ url } onClick={ clicker }>{ EXPORT_NAME[ modType ] }</a>;
};

const Loader = () => {
	return (
		<div className="loader-wrapper">
			<div className="loading loading-small">
			</div>
		</div>
	);
};

class LogModule extends React.Component {
	constructor( props ) {
		super( props );

		this.state = { showing: false, modType: false };
		this.onClick = this.handleClick.bind( this );
		this.onClose = this.handleClose.bind( this );
		this.onDownload = this.handleDownload.bind( this );
		this.onSave = this.handleSave.bind( this );
	}

	handleClose() {
		this.setState( { showing: false } );
	}

	handleDownload() {
		this.setState( { showing: false } );
		this.props.onDownloadFile( getUrl( this.props.item.name, this.state.modType ) );
	}

	handleClick( modName, modType ) {
		if ( modType !== 'config' && ( ! this.props.item.data || this.state.modType !== modType ) ) {
			this.props.onGetData( modName, modType );
		}

		this.setState( {
			showing: this.state.showing ? false : modName,
			modType: modType,
		} );
	}

	handleSave( modName, params ) {
		this.props.onSetData( modName, params );
		this.setState( { showing: false } );
	}

	getMenu( name, redirects ) {
		if ( redirects > 0 ) {
			return EXPORTS[ name ]
				.map( ( item, pos ) => exporter( item, name, pos, this.onClick ) )
				.reduce( ( prev, curr ) => [ prev, ' | ', curr ] );
		}

		if ( name === 'apache' && redirects === 0 ) {
			return exporter( 'config', 'apache', 0, this.onClick );
		}

		return null;
	}

	render() {
		const { name, redirects, data, isLoading } = this.props.item;
		const menu = this.getMenu( name, redirects );
		const total = redirects === null ? '-' : redirects;
		let showItem;

		if ( this.state.showing ) {
			if ( this.state.modType === 'config' ) {
				showItem = <ApacheConfigure onClose={ this.onClose } data={ data } onSave={ this.onSave } />;
			} else {
				showItem = <ModuleData data={ data } onClose={ this.onClose } onDownload={ this.onDownload } isLoading={ isLoading } />;
			}
		}

		return (
			<tr>
				<td className="module-contents">
					<p><strong>{ moduleName( name ) }</strong></p>
					<p>{ description( name ) }</p>

					{ this.state.showing ? showItem : <RowActions>{ menu }</RowActions> }
				</td>
				<td>
					{ isLoading ? <Loader /> : total }
				</td>
			</tr>
		);
	}
}

function mapDispatchToProps( dispatch ) {
	return {
		onGetData: ( name, type ) => {
			dispatch( getModule( name, type ) );
		},
		onSetData: ( name, data ) => {
			dispatch( setModule( name, data ) );
		},
		onDownloadFile: url => {
			dispatch( downloadFile( url ) );
		},
	};
}

export default connect(
	null,
	mapDispatchToProps,
)( LogModule );