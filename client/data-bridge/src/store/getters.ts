import { Store } from 'vuex';
import Status from '@/definitions/ApplicationStatus';
import Application, { InitializedApplicationState } from '@/store/Application';
import {
	NS_ENTITY,
	NS_STATEMENTS,
} from '@/store/namespaces';
import Term from '@/datamodel/Term';
import Statement from '@/datamodel/Statement';
import Reference from '@/datamodel/Reference';
import deepEqual from 'deep-equal';
import ApplicationStatus from '@/definitions/ApplicationStatus';
import { Context, Getters } from 'vuex-smart-module';
import { statementModule } from '@/store/statements';
import errorPropertyNameReplacer from '@/utils/errorPropertyNameReplacer';

export class RootGetters extends Getters<Application> {

	private statementModule!: Context<typeof statementModule>;

	public $init( store: Store<Application> ): void {
		this.statementModule = statementModule.context( store );
	}

	public get targetLabel(): Term {
		if ( this.state.targetLabel === null ) {
			return {
				language: 'zxx',
				value: this.state.targetProperty,
			};
		}

		return this.state.targetLabel;
	}

	public get targetReferences(): Reference[] {
		try {
			const activeState = this.state as InitializedApplicationState;
			const entityId = activeState[ NS_ENTITY ].id;
			const statements = activeState[ NS_STATEMENTS ][ entityId ][ this.state.targetProperty ][ 0 ];

			return statements.references ? statements.references : [];
		} catch ( _ignored ) {
			return [];
		}
	}

	public get isTargetValueModified(): boolean {
		if ( this.state.applicationStatus === Status.INITIALIZING ) {
			return false;
		}

		const initState = this.state as InitializedApplicationState;
		const entityId = initState[ NS_ENTITY ].id;
		return !deepEqual(
			this.state.targetValue,
			( initState[ NS_STATEMENTS ][ entityId ][ this.state.targetProperty ][ 0 ] as Statement )
				.mainsnak
				.datavalue,
			{ strict: true },
		);
	}

	public get canStartSaving(): boolean {
		return this.state.editDecision !== null &&
			this.getters.isTargetValueModified &&
			this.getters.applicationStatus === ApplicationStatus.READY;
	}

	public get applicationStatus(): ApplicationStatus {
		if ( this.state.applicationErrors.length > 0 ) {
			return ApplicationStatus.ERROR;
		}

		return this.state.applicationStatus;
	}

	public get reportIssueTemplateBody(): string {
		const pageUrl = this.state.pageUrl;
		const stackTrace = JSON.stringify( this.state.applicationErrors, errorPropertyNameReplacer, 4 );
		const activeState = this.state as InitializedApplicationState;
		const entityId = activeState[ NS_ENTITY ].id;

		return [ `The error happened on: ${pageUrl}`,
			`Item: ${entityId}`,
			`Property: ${this.state.targetProperty}`,
			`Error message: ${this.state.applicationErrors[ 0 ].type}`,
			'Debug information:',
			'```',
			stackTrace,
			'```',
		].join( '\n' );
	}

}
