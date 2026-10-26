import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createCardActivation } from '../../resources/js/cardActivation.js';

test('one tap highlights, two rapid taps confirm once', () => {
    const gesture = createCardActivation();
    assert.equal(gesture.tap('card-a', 100), false);
    assert.equal(gesture.tap('card-a', 350), true);
    assert.equal(gesture.tap('card-a', 400), false);
});

test('slow taps and different cards do not confirm', () => {
    const gesture = createCardActivation();
    assert.equal(gesture.tap('card-a', 100), false);
    assert.equal(gesture.tap('card-a', 800), false);
    assert.equal(gesture.tap('card-b', 850), false);
    assert.equal(gesture.tap('card-b', 1100), true);
});

test('new round or reset cannot reuse the previous tap', () => {
    const gesture = createCardActivation();
    gesture.tap('old-round', 100);
    assert.equal(gesture.tap('new-round', 200), false);
    gesture.reset();
    assert.equal(gesture.tap('new-round', 250), false);
});
