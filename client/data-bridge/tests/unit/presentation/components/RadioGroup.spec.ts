import RadioGroup from '@/presentation/components/RadioGroup.vue';
import { shallowMount } from '@vue/test-utils';

describe( 'RadioGroup', () => {
	it( 'is a Vue instance', () => {
		const wrapper = shallowMount( RadioGroup, {
			propsData: { title: 'title' },
		} );
		expect( wrapper.isVueInstance() ).toBeTruthy();
	} );

	it( 'gets its content through the default slot', () => {
		const content = 'some content';
		const wrapper = shallowMount( RadioGroup, {
			propsData: { title: 'title' },
			slots: { default: content },
		} );
		expect( wrapper.text() ).toBe( content );
	} );
} );
