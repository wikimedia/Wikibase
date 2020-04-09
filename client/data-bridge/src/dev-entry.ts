/* eslint no-console: "off" */
import SpecialPageReadingEntityRepository from '@/data-access/SpecialPageReadingEntityRepository';
import MwLanguageInfoRepository from '@/data-access/MwLanguageInfoRepository';
import Entity from '@/datamodel/Entity';
import Entities from '@/mock-data/data/Q42.data.json';
import EditFlow from '@/definitions/EditFlow';
import getOrEnforceUrlParameter from '@/mock-data/getOrEnforceUrlParameter';
import ServiceContainer from '@/services/ServiceContainer';
import { launch } from '@/main';
import EntityRevision from '@/datamodel/EntityRevision';
import { initEvents } from '@/events';
import MessageKeys from '@/definitions/MessageKeys';
import clone from '@/store/clone';
import messages from '@/mock-data/messages';
import Reference from '@/datamodel/Reference';

const services = new ServiceContainer();

services.set( 'readingEntityRepository', new SpecialPageReadingEntityRepository(
	{
		get: () => new Promise( ( resolve ) => {
			setTimeout( () => {
				resolve( Entities );
			}, 1100 );
		} ),
	} as any, // eslint-disable-line @typescript-eslint/no-explicit-any
	'',
) );

services.set( 'writingEntityRepository', {
	saveEntity( entity: Entity, base?: EntityRevision ): Promise<EntityRevision> {
		console.info( 'saving', entity );
		const result: EntityRevision = {
			entity: clone( entity ),
			revisionId: ( base?.revisionId || 0 ) + 1,
		};
		return new Promise( ( resolve ) => {
			setTimeout( () => {
				console.info( 'saved' );
				resolve( result );
			}, 2000 );
		} );
	},
} );

services.set( 'languageInfoRepository', new MwLanguageInfoRepository(
	{
		bcp47() {
			return 'de';
		},
	},
	{
		getDir() {
			return 'ltr';
		},
	},
) );

services.set( 'entityLabelRepository', {
	getLabel( _x ) {
		return Promise.resolve( { language: 'de', value: 'Kartoffel' } );
	},
} );

services.set( 'messagesRepository', {
	get( messageKey: MessageKeys ): string {
		return messages[ messageKey ] || `⧼${messageKey}⧽`;
	},
} );

services.set( 'wikibaseRepoConfigRepository', {
	async getRepoConfiguration() {
		return {
			dataTypeLimits: {
				string: {
					maxLength: 200,
				},
			},
			dataRightsUrl: 'https://creativecommons.org/publicdomain/zero/1.0/',
			dataRightsText: 'Creative Commons CC0',
			termsOfUseUrl: 'https://foundation.wikimedia.org/wiki/Terms_of_Use',
		};
	},
} );

services.set( 'propertyDatatypeRepository', {
	getDataType: async ( _id ) => 'string',
} );

services.set( 'tracker', {
	trackPropertyDatatype( datatype: string ) {
		console.info( `Tracking datatype: '${datatype}'` );
	},
	trackTitlePurgeError() {
		console.info( 'Tracking purge error' );
	},
	trackUnknownError( type: string ): void {
		console.info( `Tracking unknown error: '${type}'` );
	},
} );

services.set( 'editAuthorizationChecker', {
	canUseBridgeForItemAndPage: () => Promise.resolve( [] ),
} );

services.set( 'repoRouter', {
	getPageUrl: ( title, params? ) => {
		let url = `http://repo/${title}`;
		if ( params ) {
			url += '?' + new URLSearchParams( params as Record<string, string> ).toString();
		}
		return url;
	},
} );

services.set( 'clientRouter', {
	getPageUrl( title: string, params?: Record<string, unknown> ) {
		let url = `https://client.wiki.example/wiki/${title}`;
		if ( params ) {
			url += '?' + new URLSearchParams( params as Record<string, string> ).toString();
		}
		return url;
	},
} );

services.set( 'referencesRenderingRepository', {
	getRenderedReferences( references: Reference[] ): Promise<string[]> {
		return Promise.resolve( references.map( ( reference ) => {
			return `<span>${JSON.stringify( reference.snaks )}</span>`;
		} ) );
	},
} );

services.set( 'purgeTitles', {
	purge( titles: string[] ): Promise<void> {
		console.info( 'purging', titles );
		return new Promise( ( resolve ) => {
			setTimeout( () => {
				console.info( 'purged' );
				resolve();
			}, 1337 );
		} );
	},
} );

launch(
	{
		containerSelector: '#data-bridge-container',
	},
	{
		pageTitle: 'Client_page',
		entityId: 'Q42',
		propertyId: getOrEnforceUrlParameter( 'propertyId', 'P373' ) as string,
		entityTitle: 'Q42',
		editFlow: EditFlow.OVERWRITE,
		client: {
			usePublish: getOrEnforceUrlParameter( 'usePublish', 'false' ) === 'true',
			issueReportingLink: 'https://http.cat/404',
		},
		originalHref: 'https://example.com/index.php?title=Item:Q47&uselang=en#P20',
		pageUrl: 'https://client.example/wiki/Client_page',
	},
	services,
).on( initEvents.onSaved, () => {
	console.info( 'Application event: saved' );
} ).on( initEvents.onCancel, () => {
	console.info( 'Application event: canceled' );
} );
