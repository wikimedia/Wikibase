import { storiesOf } from '@storybook/vue';
import ErrorPermission from '@/presentation/components/ErrorPermission';
import useStore from './useStore';

storiesOf( 'ErrorPermission', module )
	.addParameters( { component: ErrorPermission } )
	.addDecorator( useStore( {
		entityTitle: 'Q42',
	} ) )
	.add( 'base view', () => ( {
		components: { ErrorPermission },
		template: `<ErrorPermission
				:permissionErrors="[
					{
						type: 'protectedpage',
						info: {
							right: 'editprotected',
						},
					},
					{
						type: 'cascadeprotected',
						info: {
							pages: [
								'Important Page',
								'Super Duper Important Page',
							],
						},
					},
				]"
				/>`,
	} ) );