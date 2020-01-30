import { storiesOf } from '@storybook/vue';
import ErrorUnsupportedDatatype from '../src/presentation/components/ErrorUnsupportedDatatype';
import useStore from './useStore';

storiesOf( 'ErrorUnsupportedDatatype', module )
	.addParameters( { component: ErrorUnsupportedDatatype } )
	.addDecorator( useStore( {
		entityTitle: 'Q7186',
		pageTitle: 'Marie_Curie',
		originalHref: 'https://repo.wiki.example/wiki/Item:Q7186?uselang=en',
	} ) )
	.add( 'default', () => ( {
		components: { ErrorUnsupportedDatatype },
		template: '<ErrorUnsupportedDatatype data-type="time" />',
	} ) );
