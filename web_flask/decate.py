# -*- coding:utf-8 -*-
from sklearn.feature_extraction.text import CountVectorizer, TfidfTransformer
from sklearn.neural_network import MLPClassifier
from sklearn.naive_bayes import GaussianNB
from sklearn import metrics, svm
from sklearn.model_selection import train_test_split
from sklearn.externals import joblib

import os
import numpy as np
import pickle


#%%
def load_file(file_path):
    t = b''
    with open(file_path, "rb") as f:
        for line in f:
            line = line.strip(b'\r\n')
            t += line
    return t
#%%

def check(clf, cv, transformer, path, filename):
    file_full_path = path + filename
    t = load_file(file_full_path)
    t_list = list()
    t_list.append(t)
    x = cv.transform(t_list).toarray()
    x = transformer.transform(x).toarray()
    y_pred = clf.predict(x)
    if y_pred[0] == 1:
        return True
    else:
        return False

#%%
