import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex, { Store } from 'vuex';
import Application from '@/store/Application';
import { createStore } from '@/store';
import TaintedIcon from '@/presentation/components/TaintedIcon.vue';
import { POPPER_SHOW } from '@/store/actionTypes';

const localVue = createLocalVue();
localVue.use( Vuex );

describe( 'TaintedIcon.vue', () => {
	it( 'should render the icon', () => {
		const store: Store<Application> = createStore();
		const wrapper = shallowMount( TaintedIcon, {
			store,
			localVue,
		} );
		expect( wrapper.element.tagName ).toEqual( 'A' );
		expect( wrapper.classes() ).toContain( 'wb-tr-tainted-icon' );
	} );
	it( 'opens the popper on click', () => {
		const store: Store<Application> = createStore();
		store.dispatch = jest.fn();
		const parentComponentStub = {
			name: 'parentStub',
			template: '<div></div>',
			data: () => {
				return { id: 'a-guid' };
			},
		};

		const wrapper = shallowMount( TaintedIcon, {
			store,
			localVue,
			parentComponent: parentComponentStub,
		} );
		wrapper.trigger( 'click' );
		expect( store.dispatch ).toHaveBeenCalledWith( POPPER_SHOW, 'a-guid' );
	} );
	it( 'is an un-clickable div if the popper is open', () => {
		const store: Store<Application> = createStore();
		store.dispatch( POPPER_SHOW, 'a-guid' );

		store.dispatch = jest.fn();
		const parentComponentStub = {
			name: 'parentStub',
			template: '<div></div>',
			data: () => {
				return { id: 'a-guid' };
			},
		};

		const wrapper = shallowMount( TaintedIcon, {
			store,
			localVue,
			parentComponent: parentComponentStub,
		} );

		expect( wrapper.element.tagName ).toEqual( 'DIV' );
		expect( wrapper.classes() ).toContain( 'wb-tr-tainted-icon' );

		wrapper.trigger( 'click' );
		expect( store.dispatch ).not.toHaveBeenCalledWith( POPPER_SHOW, 'a-guid' );
	} );
} );
