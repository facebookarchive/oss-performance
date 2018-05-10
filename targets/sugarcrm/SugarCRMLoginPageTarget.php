<?hh
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 *
 */

final class SugarCRMLoginPageTarget extends SugarCRMTarget {

  <<__Override>>
  protected function getSanityCheckString(): string {
    return 'User Name:';
  }
}
