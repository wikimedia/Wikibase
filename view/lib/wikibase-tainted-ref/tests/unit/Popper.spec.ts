import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex, { Store } from 'vuex';
import Message from '@/vue-plugins/Message';
import Application from '@/store/Application';
import { createStore } from '@/store';
import Popper from '@/presentation/components/Popper.vue';
import { POPPER_HIDE } from '@/store/actionTypes';

const localVue = createLocalVue();
localVue.use( Vuex );
localVue.use( Message, { messageToTextFunction: () => {
	return 'dummy';
} } );

describe( 'Popper.vue', () => {
	it( 'should render the Popper', () => {
		const store: Store<Application> = createStore();
		const wrapper = shallowMount( Popper, {
			store,
			localVue,
		} );
		expect( wrapper.classes() ).toContain( 'wb-tr-popper-wrapper' );
	} );
	it( 'closes the popper when the x is clicked', () => {
		const store: Store<Application> = createStore();
		store.dispatch = jest.fn();

		const wrapper = shallowMount( Popper, {
			store,
			localVue,
			propsData: { guid: 'a-guid' },
		} );
		wrapper.find( '.wb-tr-popper-close' ).trigger( 'click' );
		expect( store.dispatch ).toHaveBeenCalledWith( POPPER_HIDE, 'a-guid' );
	} );
	it( 'closes the popper when the focus is lost', () => {
		const store: Store<Application> = createStore();
		store.dispatch = jest.fn();

		const wrapper = shallowMount( Popper, {
			store,
			localVue,
			propsData: { guid: 'a-guid' },
		} );
		wrapper.trigger( 'focusout' );
		expect( store.dispatch ).toHaveBeenCalledWith( POPPER_HIDE, 'a-guid' );
	} );
	it( 'should use injected title text', () => {
		const localVue = createLocalVue();
		const store: Store<Application> = createStore();
		store.dispatch = jest.fn();
		const messageToTextFunction = ( key: any ): string => `(${key})`;
		localVue.use( Vuex );
		localVue.use( Message, { messageToTextFunction } );
		const wrapper = shallowMount( Popper, {
			store,
			localVue,
			propsData: { guid: 'a-guid', title: 'kitten' },
		} );

		expect( wrapper.find( '.wb-tr-popper-title' ).element.textContent )
			.toMatch( 'kitten' );
	} );
	it( 'should display the injected slots', () => {
		const localVue = createLocalVue();
		const store: Store<Application> = createStore();
		store.dispatch = jest.fn();
		const messageToTextFunction = ( key: any ): string => `(${key})`;
		localVue.use( Vuex );
		localVue.use( Message, { messageToTextFunction } );
		const wrapper = shallowMount( Popper, {
			store,
			localVue,
			propsData: { guid: 'a-guid', title: 'title' },
			slots: {
				'subheading-area': '<div class="the-subheading">subhead</div>',
				content: '<div class="the-content">content</div>',
			},
		} );

		expect( wrapper.find( '.the-subheading' ).element.textContent )
			.toMatch( 'subhead' );
		expect( wrapper.find( '.the-content' ).element.textContent )
			.toMatch( 'content' );
	} );

} );